<?php

namespace App\Services\MarketData;

use App\Models\Instrument;
use App\Models\Quote;
use Illuminate\Support\Carbon;

class QuoteService
{
    public function __construct(private readonly MarketDataProvider $provider)
    {
    }

    /**
     * Fetch the latest ticks and upsert them into the quotes table.
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

        $ticks = $this->provider->fetch($instruments);
        $now = Carbon::now();
        $updated = 0;

        foreach ($instruments as $instrument) {
            $tick = $ticks[$instrument->symbol] ?? null;
            if ($tick === null) {
                continue;
            }

            Quote::updateOrCreate(
                ['instrument_id' => $instrument->id],
                [
                    'bid'       => $tick['bid'],
                    'ask'       => $tick['ask'],
                    'quoted_at' => $now,
                ],
            );
            $updated++;
        }

        return $updated;
    }
}
