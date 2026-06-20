<?php

namespace App\Services\Trading;

use App\Models\Order;
use App\Models\Position;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Server-side matching engine. On each market tick it:
 *   1. fills pending orders whose trigger price has been reached, and
 *   2. closes open positions that hit their Stop Loss or Take Profit.
 *
 * Invoked from the `quotes:poll` command after prices are refreshed.
 */
class TradingEngine
{
    public function __construct(
        private readonly TradingService $trading,
        private readonly JournalService $journal,
    ) {
    }

    /**
     * @return array{filled: int, expired: int, closed: int}
     */
    public function tick(): array
    {
        $pending = $this->processPendingOrders();

        return [
            'filled'  => $pending['filled'],
            'expired' => $pending['expired'],
            'closed'  => $this->processStopsAndTargets(),
        ];
    }

    /**
     * @return array{filled: int, expired: int}
     */
    public function processPendingOrders(): array
    {
        $orders = Order::query()
            ->where('status', 'pending')
            ->with(['instrument.quote', 'tradingAccount'])
            ->get();

        $filled = 0;
        $expired = 0;
        $now = Carbon::now();

        foreach ($orders as $order) {
            if ($order->expires_at && $order->expires_at->lte($now)) {
                $order->update(['status' => 'expired']);
                $this->journal->log($order->tradingAccount, "Pending order #{$order->ticket} expired", 'warn', 'order');
                $expired++;
                continue;
            }

            $quote = $order->instrument->quote;
            if (! $quote) {
                continue;
            }

            if (! $this->isTriggered($order, $quote->bid, $quote->ask)) {
                continue;
            }

            try {
                $position = $this->trading->openPosition(
                    $order->tradingAccount,
                    $order->instrument,
                    $order->side(),
                    $order->volume,
                    [
                        'price'       => $order->price,
                        'stop_loss'   => $order->stop_loss,
                        'take_profit' => $order->take_profit,
                    ],
                );

                $order->update([
                    'status'      => 'filled',
                    'position_id' => $position->id,
                    'filled_at'   => $now,
                ]);
                $this->journal->log($order->tradingAccount, "Pending order #{$order->ticket} filled → position #{$position->ticket}", 'success', 'order');
                $filled++;
            } catch (RuntimeException $e) {
                // Typically insufficient margin — cancel the order rather than retry forever.
                $order->update(['status' => 'cancelled']);
                Log::info("Pending order #{$order->ticket} cancelled on fill: {$e->getMessage()}");
            } catch (Throwable $e) {
                Log::error("Pending order #{$order->ticket} fill failed: {$e->getMessage()}");
            }
        }

        return ['filled' => $filled, 'expired' => $expired];
    }

    public function processStopsAndTargets(): int
    {
        $positions = Position::query()
            ->where('status', 'open')
            ->where(fn ($q) => $q->whereNotNull('stop_loss')->orWhereNotNull('take_profit'))
            ->with(['instrument.quote', 'tradingAccount'])
            ->get();

        $closed = 0;

        foreach ($positions as $position) {
            $quote = $position->instrument->quote;
            if (! $quote) {
                continue;
            }

            if (! $this->shouldClose($position, $quote->bid, $quote->ask)) {
                continue;
            }

            try {
                $this->trading->closePosition($position);
                $closed++;
            } catch (Throwable $e) {
                Log::error("Auto-close of #{$position->ticket} failed: {$e->getMessage()}");
            }
        }

        return $closed;
    }

    private function isTriggered(Order $order, float $bid, float $ask): bool
    {
        return match ($order->type) {
            'buy_limit'  => $ask <= $order->price,
            'sell_limit' => $bid >= $order->price,
            'buy_stop'   => $ask >= $order->price,
            'sell_stop'  => $bid <= $order->price,
            default      => false,
        };
    }

    private function shouldClose(Position $position, float $bid, float $ask): bool
    {
        if ($position->side === 'buy') {
            // Buy closes at the bid.
            return ($position->stop_loss !== null && $bid <= $position->stop_loss)
                || ($position->take_profit !== null && $bid >= $position->take_profit);
        }

        // Sell closes at the ask.
        return ($position->stop_loss !== null && $ask >= $position->stop_loss)
            || ($position->take_profit !== null && $ask <= $position->take_profit);
    }
}
