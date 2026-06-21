<?php

namespace App\Services\Experts\Strategies;

use App\Services\Experts\Indicators;
use App\Services\Experts\Strategy;
use App\Services\Experts\StrategyContext;

/**
 * Mean-reversion on RSI: buy when oversold, sell when overbought, and close the
 * position once RSI crosses back through the 50 mid-line.
 */
class RsiReversionStrategy implements Strategy
{
    public function key(): string
    {
        return 'rsi_reversion';
    }

    public function label(): string
    {
        return 'RSI Reversion';
    }

    public function params(): array
    {
        return [
            ['key' => 'period', 'label' => 'RSI period', 'default' => 14],
            ['key' => 'oversold', 'label' => 'Oversold level', 'default' => 30],
            ['key' => 'overbought', 'label' => 'Overbought level', 'default' => 70],
        ];
    }

    public function decide(StrategyContext $ctx): array
    {
        $period = (int) $ctx->param('period', 14);
        $oversold = (float) $ctx->param('oversold', 30);
        $overbought = (float) $ctx->param('overbought', 70);

        $rsi = Indicators::rsi($ctx->closes, $period);
        if ($rsi === null) {
            return [];
        }

        // Exit when RSI reverts past the mid-line.
        if ($ctx->hasLong() && $rsi >= 50) {
            return [StrategyContext::closeAll()];
        }
        if ($ctx->hasShort() && $rsi <= 50) {
            return [StrategyContext::closeAll()];
        }

        if (! $ctx->hasPosition()) {
            if ($rsi <= $oversold) {
                return [StrategyContext::open('buy')];
            }
            if ($rsi >= $overbought) {
                return [StrategyContext::open('sell')];
            }
        }

        return [];
    }
}
