<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponses;
use App\Http\Controllers\Concerns\ResolvesTradingAccount;
use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JournalController extends Controller
{
    use ApiResponses;
    use ResolvesTradingAccount;

    public function index(Request $request): JsonResponse
    {
        $account = $this->activeAccount($request);

        $entries = JournalEntry::query()
            ->where('trading_account_id', $account->id)
            ->orderByDesc('id')
            ->take(200)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (JournalEntry $e) => [
                'level'    => $e->level,
                'category' => $e->category,
                'message'  => $e->message,
                'at'       => $e->created_at?->toIso8601String(),
            ]);

        return $this->ok($entries);
    }
}
