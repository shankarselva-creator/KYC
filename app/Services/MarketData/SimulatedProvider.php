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
        'EURUSD' => 1.08500, 'GBPUSD' => 1.27200, 'USDJPY' => 156.300,
        'USDCHF' => 0.89400, 'AUDUSD' => 0.66300, 'USDCAD' => 1.37100,
        'NZDUSD' => 0.61200, 'EURJPY' => 169.600, 'EURGBP' => 0.85300,
        'GBPJPY' => 198.700, 'AUDJPY' => 103.600, 'AUDCAD' => 0.90900,
        'AUDCHF' => 0.59300, 'AUDNZD' => 1.08300, 'CADJPY' => 114.000,
        'CHFJPY' => 174.800, 'EURAUD' => 1.63600, 'EURCAD' => 1.48700,
        'GBPAUD' => 1.91800, 'USDSEK' => 10.5500, 'USDNOK' => 10.7200,
        'USDZAR' => 18.2500, 'USDMXN' => 18.4500, 'XAUUSD' => 2330.00,
        'XAGUSD' => 29.500,
    ];

    /** Typical half-spread in pips per symbol (defaults to 0.6 otherwise). */
    private const SPREAD_PIPS = [
        'XAUUSD' => 20.0, 'XAGUSD' => 3.0,
        'USDSEK' => 20.0, 'USDNOK' => 20.0, 'USDZAR' => 25.0, 'USDMXN' => 25.0,
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

        return self::SEED_PRICES[$instrument->symbol] ?? $this->fallbackBase($instrument);
    }

    /**
     * Deterministic base price for symbols without an explicit seed, scaled to a
     * sensible magnitude for the instrument's quoting precision.
     */
    private function fallbackBase(Instrument $instrument): float
    {
        $magnitude = $instrument->digits >= 3 && $instrument->pip_size >= 0.01 ? 100.0 : 1.0;
        $jitter = (crc32($instrument->symbol) % 5000) / 10000; // 0.0–0.5

        return round($magnitude * (1 + $jitter), $instrument->digits);
    }
}
