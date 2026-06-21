<?php

namespace App\Services\Experts\Strategies;

use App\Services\Experts\Indicators;
use App\Services\Experts\Strategy;
use App\Services\Experts\StrategyContext;

/**
 * Bollinger Band breakout: go long when price closes above the upper band,
 * short below the lower band, and flatten on a return to the basis (mid-band).
 */
class BollingerBreakoutStrategy implements Strategy
{
    public function key(): string
    {
        return 'bollinger_breakout';
    }

    public function label(): string
    {
        return 'Bollinger Breakout';
    }

    public function params(): array
    {
        return [
            ['key' => 'period', 'label' => 'Period', 'default' => 20],
            ['key' => 'deviations', 'label' => 'Std deviations', 'default' => 2],
        ];
    }

    public function decide(StrategyContext $ctx): array
    {
        $period = (int) $ctx->param('period', 20);
        $dev = (float) $ctx->param('deviations', 2);

        $bands = Indicators::bollinger($ctx->closes, $period, $dev);
        if ($bands === null) {
            return [];
        }
        [$basis, $upper, $lower] = $bands;
        $price = end($ctx->closes);

        // Flatten when price returns to the basis.
        if ($ctx->hasLong() && $price <= $basis) {
            return [StrategyContext::closeAll()];
        }
        if ($ctx->hasShort() && $price >= $basis) {
            return [StrategyContext::closeAll()];
        }

        if (! $ctx->hasPosition()) {
            if ($price > $upper) {
                return [StrategyContext::open('buy')];
            }
            if ($price < $lower) {
                return [StrategyContext::open('sell')];
            }
        }

        return [];
    }
}
