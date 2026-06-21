<?php

namespace App\Services\Experts;

use App\Models\ExpertAdvisor;

/**
 * Everything a strategy needs to make a decision on one tick, plus mutable
 * per-EA $state that the runner persists between runs.
 */
class StrategyContext
{
    /**
     * @param  array<string, mixed>            $params         merged strategy params
     * @param  array<int, float>               $closes         oldest → newest
     * @param  array<int, float>               $highs
     * @param  array<int, float>               $lows
     * @param  array<int, array{id:int,side:string}>  $openPositions  this EA's open positions
     * @param  array<string, mixed>            $state
     */
    public function __construct(
        public readonly ExpertAdvisor $ea,
        public readonly array $params,
        public readonly array $closes,
        public readonly array $highs,
        public readonly array $lows,
        public readonly array $openPositions,
        public array $state = [],
    ) {
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function hasLong(): bool
    {
        return collect($this->openPositions)->contains(fn ($p) => $p['side'] === 'buy');
    }

    public function hasShort(): bool
    {
        return collect($this->openPositions)->contains(fn ($p) => $p['side'] === 'sell');
    }

    public function hasPosition(): bool
    {
        return count($this->openPositions) > 0;
    }

    /** @return array<string, mixed> */
    public static function open(string $side, ?float $sl = null, ?float $tp = null): array
    {
        return ['type' => 'open', 'side' => $side, 'sl' => $sl, 'tp' => $tp];
    }

    /** @return array<string, mixed> */
    public static function closeAll(): array
    {
        return ['type' => 'close_all'];
    }
}
