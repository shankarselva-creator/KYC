<?php

namespace App\Services\MarketData;

use App\Events\QuotesUpdated;
use App\Models\Instrument;
use App\Models\Quote;
use App\Models\Tick;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class QuoteService
{
    /** Keep at most this many ticks per instrument for the tick chart. */
    private const TICK_HISTORY_LIMIT = 300;

    public function __construct(
        private readonly MarketDataProvider $provider,
        private readonly CandleService $candles,
    ) {
    }

    /**
     * Fetch the latest ticks, upsert the latest quote, append to tick history
     * and maintain the daily-open reference used for "Daily Change %".
     *
     * @return int Number of instruments updated.
     */
    public function refresh(): int
    {
        $instruments = Instrument::query()
            ->where('is_active', true)
            ->with('quote')
            ->get();

        if ($instruments->isEmpty()) {
            return 0;
        }

        $feed = $this->provider->fetch($instruments);
        $now = Carbon::now();
        $today = $now->toDateString();
        $tickRows = [];
        $broadcast = [];
        $updated = 0;

        foreach ($instruments as $instrument) {
            $tick = $feed[$instrument->symbol] ?? null;
            if ($tick === null) {
                continue;
            }

            $mid = ($tick['bid'] + $tick['ask']) / 2;

            // Reset the day-open reference on the first tick of a new day.
            $existing = $instrument->quote;
            $dayOpen = $existing?->day_open;
            $dayOpenDate = $existing?->day_open_date?->toDateString();
            if ($dayOpen === null || $dayOpenDate !== $today) {
                $dayOpen = $mid;
            }

            Quote::updateOrCreate(
                ['instrument_id' => $instrument->id],
                [
                    'bid'           => $tick['bid'],
                    'ask'           => $tick['ask'],
                    'day_open'      => $dayOpen,
                    'day_open_date' => $today,
                    'quoted_at'     => $now,
                ],
            );

            $tickRows[] = [
                'instrument_id' => $instrument->id,
                'bid'           => $tick['bid'],
                'ask'           => $tick['ask'],
                'tick_at'       => $now,
            ];

            $broadcast[] = [
                'symbol'       => $instrument->symbol,
                'bid'          => $tick['bid'],
                'ask'          => $tick['ask'],
                'spread'       => round(($tick['ask'] - $tick['bid']) / $instrument->pip_size, 1),
                'daily_change' => $dayOpen > 0 ? round(($mid - $dayOpen) / $dayOpen * 100, 2) : null,
                'quoted_at'    => $now->toIso8601String(),
            ];

            $this->candles->ingest($instrument, $mid, $now);
            $updated++;
        }

        if ($tickRows) {
            Tick::insert($tickRows);
            $this->pruneTicks($instruments->pluck('id')->all());
        }

        // Push the tick to WebSocket subscribers. Never let a broadcasting
        // outage (e.g. Reverb not running) break the price refresh / engine.
        if ($broadcast) {
            try {
                QuotesUpdated::dispatch($broadcast);
            } catch (\Throwable $e) {
                Log::warning('Quote broadcast failed: '.$e->getMessage());
            }
        }

        return $updated;
    }

    /**
     * Drop ticks beyond the retention limit for each instrument.
     *
     * @param  array<int, int>  $instrumentIds
     */
    private function pruneTicks(array $instrumentIds): void
    {
        foreach ($instrumentIds as $id) {
            $cutoff = Tick::where('instrument_id', $id)
                ->orderByDesc('id')
                ->skip(self::TICK_HISTORY_LIMIT)
                ->take(1)
                ->value('id');

            if ($cutoff) {
                Tick::where('instrument_id', $id)->where('id', '<=', $cutoff)->delete();
            }
        }
    }
}
