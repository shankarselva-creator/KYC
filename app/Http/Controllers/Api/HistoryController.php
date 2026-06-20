<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponses;
use App\Http\Controllers\Concerns\ResolvesTradingAccount;
use App\Http\Controllers\Controller;
use App\Models\Position;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HistoryController extends Controller
{
    use ApiResponses;
    use ResolvesTradingAccount;

    /**
     * Closed trades, the balance ledger, and a summary report for the
     * Toolbox "History" tab.
     */
    public function show(Request $request): JsonResponse
    {
        $account = $this->activeAccount($request);

        $closed = $account->positions()
            ->where('status', 'closed')
            ->with('instrument:id,symbol,digits')
            ->latest('closed_at')
            ->take(200)
            ->get()
            ->map(fn (Position $p) => [
                'ticket'      => $p->ticket,
                'symbol'      => $p->instrument->symbol,
                'digits'      => $p->instrument->digits,
                'side'        => $p->side,
                'volume'      => $p->volume,
                'open_price'  => $p->open_price,
                'close_price' => $p->close_price,
                'profit'      => round($p->profit + $p->swap - $p->commission, 2),
                'opened_at'   => $p->opened_at?->toIso8601String(),
                'closed_at'   => $p->closed_at?->toIso8601String(),
            ]);

        $transactions = $account->transactions()
            ->latest()
            ->take(200)
            ->get()
            ->map(fn (Transaction $t) => [
                'type'          => $t->type,
                'amount'        => $t->amount,
                'balance_after' => $t->balance_after,
                'description'   => $t->description,
                'created_at'    => $t->created_at?->toIso8601String(),
            ]);

        $closedQuery = $account->positions()->where('status', 'closed');
        $trades = (clone $closedQuery)->count();
        $wins = (clone $closedQuery)->where('profit', '>', 0)->count();

        $summary = [
            'closed_pnl'  => round((float) (clone $closedQuery)->sum(DB::raw('profit + swap - commission')), 2),
            'deposits'    => round((float) $account->transactions()->where('type', 'deposit')->sum('amount'), 2),
            'withdrawals' => round((float) $account->transactions()->where('type', 'withdrawal')->sum('amount'), 2),
            'trades'      => $trades,
            'wins'        => $wins,
            'losses'      => $trades - $wins,
            'win_rate'    => $trades > 0 ? round($wins / $trades * 100, 1) : null,
            'currency'    => $account->currency,
        ];

        return $this->ok([
            'summary'      => $summary,
            'positions'    => $closed,
            'transactions' => $transactions,
        ]);
    }
}
