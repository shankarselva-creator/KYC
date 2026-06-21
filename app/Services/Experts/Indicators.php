<?php

namespace App\Services\Experts;

/**
 * Small indicator helpers for Expert Advisor strategies. All operate on an
 * array of closing prices ordered oldest → newest.
 */
class Indicators
{
    /** Simple moving average of the last $period values. */
    public static function sma(array $values, int $period): ?float
    {
        if ($period < 1 || count($values) < $period) {
            return null;
        }

        return array_sum(array_slice($values, -$period)) / $period;
    }

    /** Wilder's RSI over the last $period deltas. */
    public static function rsi(array $values, int $period): ?float
    {
        if (count($values) < $period + 1) {
            return null;
        }

        $slice = array_slice($values, -($period + 1));
        $gain = 0.0;
        $loss = 0.0;
        for ($i = 1; $i < count($slice); $i++) {
            $diff = $slice[$i] - $slice[$i - 1];
            if ($diff >= 0) {
                $gain += $diff;
            } else {
                $loss -= $diff;
            }
        }

        $avgGain = $gain / $period;
        $avgLoss = $loss / $period;
        if ($avgLoss == 0.0) {
            return 100.0;
        }

        $rs = $avgGain / $avgLoss;

        return 100 - (100 / (1 + $rs));
    }

    /**
     * Bollinger Bands: [basis, upper, lower] for the last $period values.
     *
     * @return array{0: float, 1: float, 2: float}|null
     */
    public static function bollinger(array $values, int $period, float $deviations): ?array
    {
        if (count($values) < $period) {
            return null;
        }

        $window = array_slice($values, -$period);
        $basis = array_sum($window) / $period;
        $variance = 0.0;
        foreach ($window as $v) {
            $variance += ($v - $basis) ** 2;
        }
        $sd = sqrt($variance / $period);

        return [$basis, $basis + $deviations * $sd, $basis - $deviations * $sd];
    }
}
