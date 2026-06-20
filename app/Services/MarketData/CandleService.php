<?php

namespace App\Services\MarketData;

use App\Models\Candle;
use App\Models\Instrument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CandleService
{
    /** Supported timeframes and their length in seconds (MN/W1 are calendar-aligned). */
    public const TIMEFRAMES = [
        'M1'  => 60,
        'M5'  => 300,
        'M15' => 900,
        'M30' => 1800,
        'H1'  => 3600,
        'H4'  => 14400,
        'D1'  => 86400,
        'W1'  => 604800,
        'MN'  => 2592000,
    ];

    /**
     * Update the live candle for every timeframe from a new mid-price tick.
     */
    public function ingest(Instrument $instrument, float $mid, Carbon $time): void
    {
        foreach (array_keys(self::TIMEFRAMES) as $tf) {
            $start = $this->bucketStart($time, $tf);

            $candle = Candle::firstOrNew([
                'instrument_id' => $instrument->id,
                'timeframe'     => $tf,
                'opened_at'     => $start,
            ]);

            if (! $candle->exists) {
                $candle->open = $mid;
                $candle->high = $mid;
                $candle->low = $mid;
                $candle->volume = 0;
            } else {
                $candle->high = max($candle->high, $mid);
                $candle->low = min($candle->low, $mid);
            }

            $candle->close = $mid;
            $candle->volume = $candle->volume + 1;
            $candle->save();
        }
    }

    /**
     * Recent candles for an instrument/timeframe, oldest first.
     *
     * @return Collection<int, Candle>
     */
    public function recent(Instrument $instrument, string $timeframe, int $limit = 300): Collection
    {
        $timeframe = $this->normalizeTimeframe($timeframe);

        return Candle::query()
            ->where('instrument_id', $instrument->id)
            ->where('timeframe', $timeframe)
            ->orderByDesc('opened_at')
            ->take($limit)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * Generate synthetic OHLC history so charts have depth on first load.
     * Anchors the final close to the instrument's current mid-price.
     */
    public function seedHistory(Instrument $instrument, string $timeframe, int $count, float $currentMid): void
    {
        $tf = $this->normalizeTimeframe($timeframe);
        $now = $this->bucketStart(Carbon::now(), $tf);
        $vol = $this->relativeVolatility($tf);
        $digits = $instrument->digits;

        // Random walk of closes ending at the current mid-price.
        $closes = array_fill(0, $count, 0.0);
        $closes[$count - 1] = $currentMid;
        for ($i = $count - 2; $i >= 0; $i--) {
            $shock = (mt_rand(-1000, 1000) / 1000) * $vol;
            $closes[$i] = max($instrument->pip_size, $closes[$i + 1] * (1 - $shock));
        }

        $rows = [];
        $nowTs = Carbon::now();
        for ($i = 0; $i < $count; $i++) {
            $open = $i === 0 ? $closes[0] * (1 - (mt_rand(-500, 500) / 1000) * $vol) : $closes[$i - 1];
            $close = $closes[$i];
            $wick = $vol * 0.6;
            $high = max($open, $close) * (1 + (mt_rand(0, 1000) / 1000) * $wick);
            $low = min($open, $close) * (1 - (mt_rand(0, 1000) / 1000) * $wick);

            $rows[] = [
                'instrument_id' => $instrument->id,
                'timeframe'     => $tf,
                'opened_at'     => $this->stepBack($now, $tf, $count - 1 - $i),
                'open'          => round($open, $digits),
                'high'          => round($high, $digits),
                'low'           => round($low, $digits),
                'close'         => round($close, $digits),
                'volume'        => mt_rand(50, 500),
                'created_at'    => $nowTs,
                'updated_at'    => $nowTs,
            ];
        }

        // Replace any existing history for this series, then bulk insert.
        Candle::where('instrument_id', $instrument->id)->where('timeframe', $tf)->delete();
        foreach (array_chunk($rows, 200) as $chunk) {
            Candle::insert($chunk);
        }
    }

    public function bucketStart(Carbon $time, string $timeframe): Carbon
    {
        $time = $time->copy()->utc();

        return match ($timeframe) {
            'W1' => $time->startOfWeek(Carbon::MONDAY),
            'MN' => $time->startOfMonth(),
            default => Carbon::createFromTimestamp(
                intdiv($time->getTimestamp(), self::TIMEFRAMES[$timeframe]) * self::TIMEFRAMES[$timeframe]
            )->utc(),
        };
    }

    private function stepBack(Carbon $from, string $timeframe, int $units): Carbon
    {
        return match ($timeframe) {
            'W1' => $from->copy()->subWeeks($units),
            'MN' => $from->copy()->subMonths($units),
            default => $from->copy()->subSeconds(self::TIMEFRAMES[$timeframe] * $units),
        };
    }

    /** Approximate per-bar relative move, scaled by timeframe length. */
    private function relativeVolatility(string $timeframe): float
    {
        return 0.0004 * sqrt(self::TIMEFRAMES[$timeframe] / 60);
    }

    public function normalizeTimeframe(string $timeframe): string
    {
        $timeframe = strtoupper($timeframe);

        return array_key_exists($timeframe, self::TIMEFRAMES) ? $timeframe : 'M5';
    }
}
