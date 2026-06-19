<?php

namespace App\Services\MarketData;

use Illuminate\Support\Collection;

interface MarketDataProvider
{
    /**
     * Fetch the latest tick for each of the given instruments.
     *
     * @param  Collection<int, \App\Models\Instrument>  $instruments
     * @return array<string, array{symbol: string, bid: float, ask: float}>
     *         Keyed by instrument symbol.
     */
    public function fetch(Collection $instruments): array;
}
