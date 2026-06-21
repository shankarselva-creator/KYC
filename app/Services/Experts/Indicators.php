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
     * EMA series aligned to the input (null until the period is seeded).
     *
     * @return array<int, float|null>
     */
    public static function emaSeries(array $values, int $period): array
    {
        $out = [];
        $k = 2 / ($period + 1);
        $prev = null;
        foreach ($values as $i => $v) {
            if ($i < $period - 1) {
                $out[] = null;
            } elseif ($i === $period - 1) {
                $prev = array_sum(array_slice($values, 0, $period)) / $period;
                $out[] = $prev;
            } else {
                $prev = $v * $k + $prev * (1 - $k);
                $out[] = $prev;
            }
        }

        return $out;
    }

    /**
     * MACD: [macdNow, signalNow, macdPrev, signalPrev], or null if insufficient data.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}|null
     */
    public static function macd(array $values, int $fast, int $slow, int $signal): ?array
    {
        if ($fast >= $slow || count($values) < $slow + $signal + 1) {
            return null;
        }

        $ef = self::emaSeries($values, $fast);
        $es = self::emaSeries($values, $slow);
        $macdLine = [];
        foreach ($values as $i => $_) {
            if ($ef[$i] !== null && $es[$i] !== null) {
                $macdLine[] = $ef[$i] - $es[$i];
            }
        }
        if (count($macdLine) < $signal + 1) {
            return null;
        }

        $sig = self::emaSeries($macdLine, $signal);
        $n = count($macdLine);
        if ($sig[$n - 1] === null || $sig[$n - 2] === null) {
            return null;
        }

        return [$macdLine[$n - 1], $sig[$n - 1], $macdLine[$n - 2], $sig[$n - 2]];
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
