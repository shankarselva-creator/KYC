<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponses;
use App\Http\Controllers\Concerns\ResolvesTradingAccount;
use App\Http\Controllers\Controller;
use App\Models\Instrument;
use App\Services\Trading\TradingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstrumentController extends Controller
{
    use ApiResponses;
    use ResolvesTradingAccount;

    /**
     * Contract specification for an instrument (the Market Watch "Specification"
     * context-menu tool): digits, contract size, swaps, and margin per lot.
     */
    public function specification(Request $request, string $symbol, TradingService $trading): JsonResponse
    {
        $instrument = Instrument::where('symbol', $symbol)->firstOrFail();
        $account = $this->activeAccount($request);
        $quote = $instrument->quote;

        return $this->ok([
            'symbol'         => $instrument->symbol,
            'description'    => $instrument->description,
            'category'       => $instrument->category,
            'base_currency'  => $instrument->base_currency,
            'quote_currency' => $instrument->quote_currency,
            'digits'         => $instrument->digits,
            'pip_size'       => $instrument->pip_size,
            'contract_size'  => $instrument->contract_size,
            'spread'         => $quote ? round(($quote->ask - $quote->bid) / $instrument->pip_size, 1) : null,
            'stops_level'    => $instrument->stops_level,
            'volume_min'     => $instrument->min_volume,
            'volume_max'     => $instrument->max_volume,
            'volume_step'    => $instrument->volume_step,
            'swap_long'      => $instrument->swap_long,
            'swap_short'     => $instrument->swap_short,
            'margin_per_lot' => $trading->marginPerLot($instrument, $account),
            'margin_currency' => $account->currency,
            'leverage'       => $account->leverage,
        ]);
    }

    /**
     * Recent tick-by-tick history for the Market Watch tick chart.
     */
    public function ticks(Request $request, string $symbol): JsonResponse
    {
        $instrument = Instrument::where('symbol', $symbol)->firstOrFail();
        $limit = min(300, max(10, (int) $request->integer('limit', 120)));

        $ticks = $instrument->ticks()
            ->orderByDesc('id')
            ->take($limit)
            ->get(['bid', 'ask', 'tick_at'])
            ->reverse()
            ->values()
            ->map(fn ($t) => [
                'bid'     => (float) $t->bid,
                'ask'     => (float) $t->ask,
                'mid'     => round(((float) $t->bid + (float) $t->ask) / 2, $instrument->digits),
                'tick_at' => $t->tick_at?->toIso8601String(),
            ]);

        return $this->ok([
            'symbol' => $instrument->symbol,
            'digits' => $instrument->digits,
            'ticks'  => $ticks,
        ]);
    }
}
