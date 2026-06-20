<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponses;
use App\Http\Controllers\Concerns\ResolvesTradingAccount;
use App\Http\Controllers\Controller;
use App\Services\Trading\FundingService;
use App\Services\Trading\TradingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AccountController extends Controller
{
    use ApiResponses;
    use ResolvesTradingAccount;

    public function show(Request $request, TradingService $trading): JsonResponse
    {
        $account = $this->activeAccount($request);
        $metrics = $trading->accountMetrics($account);

        return $this->ok([
            'login'    => $account->login,
            'name'     => $account->name,
            'type'     => $account->type,
            'currency' => $account->currency,
            'leverage' => $account->leverage,
            'metrics'  => $metrics,
        ]);
    }

    public function deposit(Request $request, FundingService $funding): JsonResponse
    {
        return $this->fund($request, $funding, 'deposit');
    }

    public function withdraw(Request $request, FundingService $funding): JsonResponse
    {
        return $this->fund($request, $funding, 'withdraw');
    }

    private function fund(Request $request, FundingService $funding, string $action): JsonResponse
    {
        $account = $this->activeAccount($request);
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:100000000'],
        ]);

        try {
            $tx = $funding->{$action}($account, (float) $validated['amount']);
        } catch (RuntimeException $e) {
            return $this->fail('FUNDING_FAILED', $e->getMessage(), 422);
        }

        return $this->ok([
            'type'          => $tx->type,
            'amount'        => $tx->amount,
            'balance_after' => $tx->balance_after,
        ]);
    }
}
