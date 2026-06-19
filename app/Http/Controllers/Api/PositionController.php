<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponses;
use App\Http\Controllers\Concerns\ResolvesTradingAccount;
use App\Http\Controllers\Controller;
use App\Models\Position;
use App\Services\Trading\TradingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PositionController extends Controller
{
    use ApiResponses;
    use ResolvesTradingAccount;

    public function index(Request $request, TradingService $trading): JsonResponse
    {
        $account = $this->activeAccount($request);

        return $this->ok($trading->openPositionsWithPnl($account));
    }

    public function close(Request $request, Position $position, TradingService $trading): JsonResponse
    {
        $account = $this->activeAccount($request);

        if ($position->trading_account_id !== $account->id) {
            return $this->fail('FORBIDDEN', 'This position does not belong to your account.', 403);
        }

        try {
            $closed = $trading->closePosition($position);
        } catch (RuntimeException $e) {
            return $this->fail('CLOSE_FAILED', $e->getMessage(), 422);
        }

        return $this->ok([
            'ticket'      => $closed->ticket,
            'close_price' => $closed->close_price,
            'profit'      => $closed->profit,
            'status'      => $closed->status,
        ]);
    }
}
