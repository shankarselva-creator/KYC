<?php

namespace App\Services\Experts;

use App\Services\Experts\Strategies\BollingerBreakoutStrategy;
use App\Services\Experts\Strategies\MacdCrossStrategy;
use App\Services\Experts\Strategies\MovingAverageCrossStrategy;
use App\Services\Experts\Strategies\RsiReversionStrategy;

class StrategyRegistry
{
    /** @var array<string, Strategy> */
    private array $strategies = [];

    public function __construct()
    {
        foreach ([
            new MovingAverageCrossStrategy(),
            new RsiReversionStrategy(),
            new BollingerBreakoutStrategy(),
            new MacdCrossStrategy(),
        ] as $strategy) {
            $this->strategies[$strategy->key()] = $strategy;
        }
    }

    public function get(string $key): ?Strategy
    {
        return $this->strategies[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->strategies[$key]);
    }

    /**
     * Catalog for the UI: each strategy with its parameter schema + defaults.
     *
     * @return array<int, array<string, mixed>>
     */
    public function catalog(): array
    {
        return array_values(array_map(fn (Strategy $s) => [
            'key'    => $s->key(),
            'label'  => $s->label(),
            'params' => $s->params(),
        ], $this->strategies));
    }

    /**
     * Default param values for a strategy key.
     *
     * @return array<string, int|float>
     */
    public function defaults(string $key): array
    {
        $strategy = $this->get($key);
        if (! $strategy) {
            return [];
        }

        $defaults = [];
        foreach ($strategy->params() as $p) {
            $defaults[$p['key']] = $p['default'];
        }

        return $defaults;
    }
}
