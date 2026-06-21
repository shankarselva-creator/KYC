<?php

namespace App\Services\Experts;

use App\Models\ExpertAdvisor;
use App\Models\Position;
use App\Services\MarketData\CandleService;
use App\Services\Trading\JournalService;
use App\Services\Trading\TradingService;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Runs every active Expert Advisor once per market tick: builds the strategy
 * context from recent candles, asks the strategy for actions, and executes them
 * (open/close) through the TradingService. Invoked from `quotes:poll`.
 */
class ExpertAdvisorRunner
{
    public function __construct(
        private readonly TradingService $trading,
        private readonly JournalService $journal,
        private readonly CandleService $candles,
        private readonly StrategyRegistry $registry,
    ) {
    }

    /**
     * @return array{opened: int, closed: int}
     */
    public function run(): array
    {
        $eas = ExpertAdvisor::query()
            ->where('is_active', true)
            ->with(['instrument.quote', 'tradingAccount'])
            ->get();

        $opened = 0;
        $closed = 0;

        foreach ($eas as $ea) {
            $strategy = $this->registry->get($ea->strategy);
            if (! $strategy || ! $ea->instrument->quote || ! $ea->tradingAccount?->is_active) {
                continue;
            }

            $candles = $this->candles->recent($ea->instrument, $ea->timeframe, 200);
            $closes = $candles->pluck('close')->map(fn ($v) => (float) $v)->all();
            if (count($closes) < 30) {
                continue;
            }

            $open = Position::where('expert_advisor_id', $ea->id)->where('status', 'open')->get(['id', 'side']);
            $ctx = new StrategyContext(
                $ea,
                array_merge($this->registry->defaults($ea->strategy), $ea->params ?? []),
                $closes,
                $candles->pluck('high')->map(fn ($v) => (float) $v)->all(),
                $candles->pluck('low')->map(fn ($v) => (float) $v)->all(),
                $open->map(fn ($p) => ['id' => $p->id, 'side' => $p->side])->all(),
                $ea->state ?? [],
            );

            try {
                $actions = $strategy->decide($ctx);
            } catch (Throwable $e) {
                Log::error("EA #{$ea->id} ({$ea->strategy}) failed: {$e->getMessage()}");
                continue;
            }

            foreach ($actions as $action) {
                $type = $action['type'] ?? null;
                if ($type === 'open') {
                    $opened += $this->openTrade($ea, $action) ? 1 : 0;
                } elseif ($type === 'close_all') {
                    $closed += $this->closeTrades($ea, $open->pluck('id')->all());
                } elseif ($type === 'close' && isset($action['position_id'])) {
                    $closed += $this->closeTrades($ea, [$action['position_id']]);
                }
            }

            $ea->update(['state' => $ctx->state, 'last_run_at' => now()]);
        }

        return ['opened' => $opened, 'closed' => $closed];
    }

    /**
     * @param  array<string, mixed>  $action
     */
    private function openTrade(ExpertAdvisor $ea, array $action): bool
    {
        try {
            $this->trading->openPosition($ea->tradingAccount, $ea->instrument, $action['side'], $ea->volume, [
                'expert_advisor_id' => $ea->id,
                'magic'             => $ea->magic,
                'stop_loss'         => $action['sl'] ?? null,
                'take_profit'       => $action['tp'] ?? null,
            ]);
            $this->journal->log($ea->tradingAccount, "EA {$ea->name}: opened {$action['side']} {$ea->volume} {$ea->instrument->symbol}", 'success', 'expert');

            return true;
        } catch (RuntimeException $e) {
            $this->journal->log($ea->tradingAccount, "EA {$ea->name}: open rejected — {$e->getMessage()}", 'warn', 'expert');

            return false;
        } catch (Throwable $e) {
            Log::error("EA #{$ea->id} open failed: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * @param  array<int, int>  $positionIds
     */
    private function closeTrades(ExpertAdvisor $ea, array $positionIds): int
    {
        $count = 0;
        foreach ($positionIds as $id) {
            $position = Position::where('id', $id)->where('status', 'open')->first();
            if (! $position) {
                continue;
            }
            try {
                $this->trading->closePosition($position);
                $this->journal->log($ea->tradingAccount, "EA {$ea->name}: closed #{$position->ticket}", 'info', 'expert');
                $count++;
            } catch (Throwable $e) {
                Log::error("EA #{$ea->id} close failed: {$e->getMessage()}");
            }
        }

        return $count;
    }
}
