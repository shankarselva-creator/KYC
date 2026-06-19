<?php

namespace App\Services\MarketData;

use App\Models\Instrument;
use Illuminate\Support\Collection;

/**
 * Generates a random-walk price feed for development / offline use.
 *
 * Each tick nudges the previous mid-price by a small random amount (bounded by
 * the configured max pip move) and rebuilds bid/ask around a typical spread.
 */
class SimulatedProvider implements MarketDataProvider
{
    /** Seed mid-prices used when an instrument has no prior quote. */
    private const SEED_PRICES = [
        'EURUSD' => 1.08500,
        'GBPUSD' => 1.27200,
        'USDJPY' => 156.300,
        'USDCHF' => 0.89400,
        'AUDUSD' => 0.66300,
        'USDCAD' => 1.37100,
        'NZDUSD' => 0.61200,
        'EURJPY' => 169.600,
        'EURGBP' => 0.85300,
        'XAUUSD' => 2330.00,
    ];

    /** Typical half-spread in pips per symbol. */
    private const SPREAD_PIPS = [
        'XAUUSD' => 20.0,
    ];

    public function fetch(Collection $instruments): array
    {
        $maxMove = (float) config('markets.simulated.max_pip_move', 3.0);
        $out = [];

        foreach ($instruments as $instrument) {
            /** @var Instrument $instrument */
            $mid = $this->currentMid($instrument);

            // Random walk: move by [-maxMove, +maxMove] pips.
            $move = (mt_rand(-1000, 1000) / 1000) * $maxMove * $instrument->pip_size;
            $mid = max($instrument->pip_size, $mid + $move);

            $halfSpread = (self::SPREAD_PIPS[$instrument->symbol] ?? 0.6) * $instrument->pip_size;
            $bid = round($mid - $halfSpread, $instrument->digits);
            $ask = round($mid + $halfSpread, $instrument->digits);

            $out[$instrument->symbol] = [
                'symbol' => $instrument->symbol,
                'bid'    => $bid,
                'ask'    => $ask,
            ];
        }

        return $out;
    }

    private function currentMid(Instrument $instrument): float
    {
        if ($instrument->relationLoaded('quote') && $instrument->quote) {
            return ($instrument->quote->bid + $instrument->quote->ask) / 2;
        }

        if ($existing = $instrument->quote) {
            return ($existing->bid + $existing->ask) / 2;
        }

        return self::SEED_PRICES[$instrument->symbol] ?? 1.0;
    }
}
