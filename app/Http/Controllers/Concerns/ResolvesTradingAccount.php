<?php

namespace App\Http\Controllers\Concerns;

use App\Models\TradingAccount;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait ResolvesTradingAccount
{
    protected function activeAccount(Request $request): TradingAccount
    {
        $account = $request->user()->primaryTradingAccount();

        if (! $account) {
            throw new HttpException(404, 'No trading account found for this user.');
        }

        return $account;
    }
}
