<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Instrument;
use Illuminate\Http\JsonResponse;

class QuoteController extends Controller
{
    use ApiResponses;

    public function index(): JsonResponse
    {
        $instruments = Instrument::query()
            ->where('is_active', true)
            ->with('quote')
            ->orderBy('sort_order')
            ->get();

        $data = $instruments->map(function (Instrument $instrument) {
            $quote = $instrument->quote;

            return [
                'symbol'        => $instrument->symbol,
                'description'   => $instrument->description,
                'category'      => $instrument->category,
                'digits'        => $instrument->digits,
                'bid'           => $quote?->bid,
                'ask'           => $quote?->ask,
                'spread'        => $quote ? round(($quote->ask - $quote->bid) / $instrument->pip_size, 1) : null,
                'daily_change'  => $quote?->daily_change,
                'quoted_at'     => $quote?->quoted_at?->toIso8601String(),
            ];
        });

        return $this->ok($data);
    }
}
