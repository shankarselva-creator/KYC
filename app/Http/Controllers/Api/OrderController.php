<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponses;
use App\Http\Controllers\Concerns\ResolvesTradingAccount;
use App\Http\Controllers\Controller;
use App\Models\Instrument;
use App\Services\Trading\TradingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class OrderController extends Controller
{
    use ApiResponses;
    use ResolvesTradingAccount;

    public function store(Request $request, TradingService $trading): JsonResponse
    {
        $account = $this->activeAccount($request);

        $validated = $request->validate([
            'symbol'      => ['required', 'string', 'exists:instruments,symbol'],
            'side'        => ['required', 'in:buy,sell'],
            'volume'      => ['required', 'numeric', 'min:0.01', 'max:100'],
            'stop_loss'   => ['nullable', 'numeric', 'gt:0'],
            'take_profit' => ['nullable', 'numeric', 'gt:0'],
        ]);

        $instrument = Instrument::where('symbol', $validated['symbol'])->where('is_active', true)->firstOrFail();

        try {
            $position = $trading->openPosition(
                $account,
                $instrument,
                $validated['side'],
                (float) $validated['volume'],
                [
                    'stop_loss'   => $validated['stop_loss'] ?? null,
                    'take_profit' => $validated['take_profit'] ?? null,
                ],
            );
        } catch (RuntimeException $e) {
            return $this->fail('ORDER_REJECTED', $e->getMessage(), 422);
        }

        return $this->ok([
            'ticket'     => $position->ticket,
            'symbol'     => $instrument->symbol,
            'side'       => $position->side,
            'volume'     => $position->volume,
            'open_price' => $position->open_price,
            'opened_at'  => $position->opened_at?->toIso8601String(),
        ]);
    }
}
