<?php

namespace App\Services\Experts\Strategies;

use App\Services\Experts\Indicators;
use App\Services\Experts\Strategy;
use App\Services\Experts\StrategyContext;

/**
 * Classic fast/slow moving-average crossover. Goes (and stays) long after a
 * bullish cross, short after a bearish cross — one position at a time.
 */
class MovingAverageCrossStrategy implements Strategy
{
    public function key(): string
    {
        return 'ma_cross';
    }

    public function label(): string
    {
        return 'Moving Average Cross';
    }

    public function params(): array
    {
        return [
            ['key' => 'fast', 'label' => 'Fast MA period', 'default' => 10],
            ['key' => 'slow', 'label' => 'Slow MA period', 'default' => 30],
        ];
    }

    public function decide(StrategyContext $ctx): array
    {
        $fast = (int) $ctx->param('fast', 10);
        $slow = (int) $ctx->param('slow', 30);
        $closes = $ctx->closes;
        if ($fast >= $slow || count($closes) < $slow + 1) {
            return [];
        }

        $prev = array_slice($closes, 0, -1); // one bar ago
        $fastNow = Indicators::sma($closes, $fast);
        $slowNow = Indicators::sma($closes, $slow);
        $fastPrev = Indicators::sma($prev, $fast);
        $slowPrev = Indicators::sma($prev, $slow);
        if ($fastNow === null || $slowNow === null || $fastPrev === null || $slowPrev === null) {
            return [];
        }

        $crossUp = $fastPrev <= $slowPrev && $fastNow > $slowNow;
        $crossDown = $fastPrev >= $slowPrev && $fastNow < $slowNow;
        $actions = [];

        if ($crossUp && ! $ctx->hasLong()) {
            if ($ctx->hasShort()) {
                $actions[] = StrategyContext::closeAll();
            }
            $actions[] = StrategyContext::open('buy');
        } elseif ($crossDown && ! $ctx->hasShort()) {
            if ($ctx->hasLong()) {
                $actions[] = StrategyContext::closeAll();
            }
            $actions[] = StrategyContext::open('sell');
        }

        return $actions;
    }
}
