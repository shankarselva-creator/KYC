<?php

namespace App\Services\Trading;

use App\Models\Instrument;
use App\Models\Position;
use App\Models\Quote;
use App\Models\TradingAccount;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TradingService
{
    /**
     * Open a market position on the given account.
     *
     * @param  array{stop_loss?: float|null, take_profit?: float|null}  $options
     */
    public function openPosition(
        TradingAccount $account,
        Instrument $instrument,
        string $side,
        float $volume,
        array $options = [],
    ): Position {
        $this->assertValidVolume($instrument, $volume);

        if (! $account->is_active) {
            throw new RuntimeException('Trading account is inactive.');
        }

        $quote = $this->quoteFor($instrument);
        $price = $side === 'buy' ? $quote->ask : $quote->bid;

        $converter = $this->converter();
        $requiredMargin = $this->requiredMargin($instrument, $volume, $price, $account->leverage, $converter, $account->currency);

        $metrics = $this->accountMetrics($account);
        if ($metrics['free_margin'] < $requiredMargin) {
            throw new RuntimeException('Not enough free margin to open this position.');
        }

        return DB::transaction(function () use ($account, $instrument, $side, $volume, $price, $options) {
            return Position::create([
                'ticket'             => $this->generateTicket(),
                'trading_account_id' => $account->id,
                'instrument_id'      => $instrument->id,
                'side'               => $side,
                'volume'             => $volume,
                'open_price'         => $price,
                'stop_loss'          => $options['stop_loss'] ?? null,
                'take_profit'        => $options['take_profit'] ?? null,
                'commission'         => 0,
                'swap'               => 0,
                'profit'             => 0,
                'status'             => 'open',
                'opened_at'          => Carbon::now(),
            ]);
        });
    }

    /**
     * Close an open position at the current market price and settle P/L.
     */
    public function closePosition(Position $position): Position
    {
        if (! $position->isOpen()) {
            throw new RuntimeException('Position is already closed.');
        }

        $position->loadMissing('instrument', 'tradingAccount');
        $instrument = $position->instrument;
        $account = $position->tradingAccount;

        $quote = $this->quoteFor($instrument);
        $closePrice = $position->side === 'buy' ? $quote->bid : $quote->ask;

        $converter = $this->converter();
        $profit = $this->realisedProfit($position, $closePrice, $converter, $account->currency);

        return DB::transaction(function () use ($position, $account, $closePrice, $profit) {
            $position->update([
                'close_price' => $closePrice,
                'profit'      => $profit,
                'status'      => 'closed',
                'closed_at'   => Carbon::now(),
            ]);

            $netAmount = $profit + $position->swap - $position->commission;
            $newBalance = round($account->balance + $netAmount, 2);
            $account->update(['balance' => $newBalance]);

            Transaction::create([
                'trading_account_id' => $account->id,
                'position_id'        => $position->id,
                'type'               => 'trade',
                'amount'             => $netAmount,
                'balance_after'      => $newBalance,
                'description'        => "Closed #{$position->ticket} {$position->side} {$position->volume} {$position->instrument->symbol}",
            ]);

            return $position->refresh();
        });
    }

    /**
     * Open positions decorated with live current price and floating P/L.
     *
     * @return array<int, array<string, mixed>>
     */
    public function openPositionsWithPnl(TradingAccount $account): array
    {
        $positions = $account->openPositions()->with('instrument.quote')->latest('opened_at')->get();
        $converter = $this->converter();

        return $positions->map(function (Position $position) use ($converter, $account) {
            $quote = $position->instrument->quote;
            $currentPrice = $quote
                ? ($position->side === 'buy' ? $quote->bid : $quote->ask)
                : $position->open_price;
            $profit = $quote
                ? $this->realisedProfit($position, $currentPrice, $converter, $account->currency)
                : 0.0;

            return [
                'id'            => $position->id,
                'ticket'        => $position->ticket,
                'symbol'        => $position->instrument->symbol,
                'side'          => $position->side,
                'volume'        => $position->volume,
                'open_price'    => $position->open_price,
                'current_price' => $currentPrice,
                'stop_loss'     => $position->stop_loss,
                'take_profit'   => $position->take_profit,
                'swap'          => $position->swap,
                'commission'    => $position->commission,
                'profit'        => $profit,
                'opened_at'     => $position->opened_at?->toIso8601String(),
            ];
        })->all();
    }

    /**
     * Compute live account metrics (equity, margin, free margin, margin level).
     *
     * @return array{
     *     balance: float, floating_pnl: float, equity: float,
     *     used_margin: float, free_margin: float, margin_level: float|null
     * }
     */
    public function accountMetrics(TradingAccount $account): array
    {
        $positions = $account->openPositions()->with('instrument.quote')->get();
        $converter = $this->converter();

        $floating = 0.0;
        $usedMargin = 0.0;

        foreach ($positions as $position) {
            $quote = $position->instrument->quote;
            if (! $quote) {
                continue;
            }

            $currentPrice = $position->side === 'buy' ? $quote->bid : $quote->ask;
            $floating += $this->realisedProfit($position, $currentPrice, $converter, $account->currency);
            $usedMargin += $this->requiredMargin(
                $position->instrument,
                $position->volume,
                $position->open_price,
                $account->leverage,
                $converter,
                $account->currency,
            );
        }

        $floating = round($floating, 2);
        $usedMargin = round($usedMargin, 2);
        $equity = round($account->balance + $floating, 2);
        $freeMargin = round($equity - $usedMargin, 2);
        $marginLevel = $usedMargin > 0 ? round($equity / $usedMargin * 100, 2) : null;

        return [
            'balance'      => round($account->balance, 2),
            'floating_pnl' => $floating,
            'equity'       => $equity,
            'used_margin'  => $usedMargin,
            'free_margin'  => $freeMargin,
            'margin_level' => $marginLevel,
        ];
    }

    /**
     * Profit (in account currency) of a position at the given exit price.
     */
    public function realisedProfit(Position $position, float $exitPrice, CurrencyConverter $converter, string $accountCurrency): float
    {
        $instrument = $position->instrument;
        $direction = $position->side === 'buy' ? 1 : -1;

        $profitQuote = ($exitPrice - $position->open_price) * $direction
            * $position->volume * $instrument->contract_size;

        return round($converter->convert($profitQuote, $instrument->quote_currency, $accountCurrency), 2);
    }

    /**
     * Margin required (in account currency) for a position.
     */
    public function requiredMargin(
        Instrument $instrument,
        float $volume,
        float $price,
        int $leverage,
        CurrencyConverter $converter,
        string $accountCurrency,
    ): float {
        $leverage = max(1, $leverage);
        // Notional is denominated in the base currency.
        $notionalBase = $volume * $instrument->contract_size;
        $notionalAccount = $converter->convert($notionalBase, $instrument->base_currency, $accountCurrency);

        return round($notionalAccount / $leverage, 2);
    }

    private function quoteFor(Instrument $instrument): Quote
    {
        $quote = $instrument->quote()->first();
        if (! $quote) {
            throw new RuntimeException("No live price available for {$instrument->symbol}.");
        }

        return $quote;
    }

    private function converter(): CurrencyConverter
    {
        $prices = Quote::query()
            ->with('instrument:id,symbol')
            ->get()
            ->mapWithKeys(fn (Quote $q) => [
                $q->instrument->symbol => ($q->bid + $q->ask) / 2,
            ])
            ->all();

        return new CurrencyConverter($prices);
    }

    private function assertValidVolume(Instrument $instrument, float $volume): void
    {
        if ($volume < $instrument->min_volume || $volume > $instrument->max_volume) {
            throw new RuntimeException(
                "Volume must be between {$instrument->min_volume} and {$instrument->max_volume} lots."
            );
        }
    }

    private function generateTicket(): int
    {
        do {
            $ticket = random_int(10_000_000, 99_999_999);
        } while (Position::where('ticket', $ticket)->exists());

        return $ticket;
    }
}
