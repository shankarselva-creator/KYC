<?php

namespace App\Services\Experts;

interface Strategy
{
    /** Stable key stored on the EA (e.g. "ma_cross"). */
    public function key(): string;

    /** Human-readable name for the UI. */
    public function label(): string;

    /**
     * Configurable parameters with defaults, for the UI form and runtime merge.
     *
     * @return array<int, array{key: string, label: string, default: int|float}>
     */
    public function params(): array;

    /**
     * Decide what to do this tick.
     *
     * @return array<int, array<string, mixed>>  list of action arrays
     */
    public function decide(StrategyContext $ctx): array;
}
