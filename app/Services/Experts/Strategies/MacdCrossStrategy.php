<?php

namespace App\Services\Experts\Strategies;

use App\Services\Experts\Indicators;
use App\Services\Experts\Strategy;
use App\Services\Experts\StrategyContext;

/**
 * MACD signal-line crossover: go long when the MACD line crosses above its
 * signal line, short when it crosses below — one position at a time.
 */
class MacdCrossStrategy implements Strategy
{
    public function key(): string
    {
        return 'macd_cross';
    }

    public function label(): string
    {
        return 'MACD Cross';
    }

    public function params(): array
    {
        return [
            ['key' => 'fast', 'label' => 'Fast EMA', 'default' => 12],
            ['key' => 'slow', 'label' => 'Slow EMA', 'default' => 26],
            ['key' => 'signal', 'label' => 'Signal EMA', 'default' => 9],
        ];
    }

    public function decide(StrategyContext $ctx): array
    {
        $macd = Indicators::macd(
            $ctx->closes,
            (int) $ctx->param('fast', 12),
            (int) $ctx->param('slow', 26),
            (int) $ctx->param('signal', 9),
        );
        if ($macd === null) {
            return [];
        }
        [$macdNow, $sigNow, $macdPrev, $sigPrev] = $macd;

        // Tolerance so floating-point noise around a flat (≈0) histogram doesn't
        // mask a genuine crossover.
        $eps = 1e-9;
        $prevDiff = $macdPrev - $sigPrev;
        $nowDiff = $macdNow - $sigNow;
        $crossUp = $prevDiff <= $eps && $nowDiff > $eps;
        $crossDown = $prevDiff >= -$eps && $nowDiff < -$eps;

        if ($crossUp && ! $ctx->hasLong()) {
            return array_merge($ctx->hasShort() ? [StrategyContext::closeAll()] : [], [StrategyContext::open('buy')]);
        }
        if ($crossDown && ! $ctx->hasShort()) {
            return array_merge($ctx->hasLong() ? [StrategyContext::closeAll()] : [], [StrategyContext::open('sell')]);
        }

        return [];
    }
}
