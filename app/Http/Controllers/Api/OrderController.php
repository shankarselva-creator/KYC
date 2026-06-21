<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponses;
use App\Http\Controllers\Concerns\ResolvesTradingAccount;
use App\Http\Controllers\Controller;
use App\Models\Instrument;
use App\Models\Order;
use App\Services\Trading\TradingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class OrderController extends Controller
{
    use ApiResponses;
    use ResolvesTradingAccount;

    private const PENDING_TYPES = ['buy_limit', 'sell_limit', 'buy_stop', 'sell_stop'];

    public function index(Request $request, TradingService $trading): JsonResponse
    {
        $account = $this->activeAccount($request);

        return $this->ok($trading->pendingOrdersList($account));
    }

    public function store(Request $request, TradingService $trading): JsonResponse
    {
        $account = $this->activeAccount($request);

        $validated = $request->validate([
            // buy/sell = market execution; the four *_limit/*_stop = pending.
            'type'        => ['required', 'in:buy,sell,buy_limit,sell_limit,buy_stop,sell_stop'],
            'symbol'      => ['required', 'string', 'exists:instruments,symbol'],
            'volume'      => ['required', 'numeric', 'min:0.01', 'max:100'],
            'price'       => ['nullable', 'numeric', 'gt:0', 'required_if:type,buy_limit,sell_limit,buy_stop,sell_stop'],
            'stop_loss'   => ['nullable', 'numeric', 'gt:0'],
            'take_profit' => ['nullable', 'numeric', 'gt:0'],
        ]);

        $instrument = Instrument::where('symbol', $validated['symbol'])->where('is_active', true)->firstOrFail();
        $options = [
            'stop_loss'   => $validated['stop_loss'] ?? null,
            'take_profit' => $validated['take_profit'] ?? null,
        ];

        try {
            if (in_array($validated['type'], self::PENDING_TYPES, true)) {
                $order = $trading->placePendingOrder(
                    $account, $instrument, $validated['type'],
                    (float) $validated['volume'], (float) $validated['price'], $options,
                );

                return $this->ok([
                    'kind'   => 'pending',
                    'ticket' => $order->ticket,
                    'symbol' => $instrument->symbol,
                    'type'   => $order->type,
                    'volume' => $order->volume,
                    'price'  => $order->price,
                ]);
            }

            $position = $trading->openPosition(
                $account, $instrument, $validated['type'], (float) $validated['volume'], $options,
            );
        } catch (RuntimeException $e) {
            return $this->fail('ORDER_REJECTED', $e->getMessage(), 422);
        }

        return $this->ok([
            'kind'       => 'market',
            'ticket'     => $position->ticket,
            'symbol'     => $instrument->symbol,
            'side'       => $position->side,
            'volume'     => $position->volume,
            'open_price' => $position->open_price,
            'opened_at'  => $position->opened_at?->toIso8601String(),
        ]);
    }

    public function cancel(Request $request, Order $order, TradingService $trading): JsonResponse
    {
        $account = $this->activeAccount($request);

        if ($order->trading_account_id !== $account->id) {
            return $this->fail('FORBIDDEN', 'This order does not belong to your account.', 403);
        }

        try {
            $trading->cancelPendingOrder($order);
        } catch (RuntimeException $e) {
            return $this->fail('CANCEL_FAILED', $e->getMessage(), 422);
        }

        return $this->ok(['ticket' => $order->ticket, 'status' => $order->status]);
    }
}
