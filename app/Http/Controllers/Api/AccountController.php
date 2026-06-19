<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponses;
use App\Http\Controllers\Concerns\ResolvesTradingAccount;
use App\Http\Controllers\Controller;
use App\Services\Trading\TradingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
