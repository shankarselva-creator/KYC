<?php

namespace App\Services\Trading;

use App\Models\TradingAccount;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AccountProvisioner
{
    /**
     * Create a funded demo trading account for a freshly registered user.
     */
    public function createDemoAccount(User $user): TradingAccount
    {
        $balance = (float) config('markets.trading.demo_balance', 10000);
        $currency = config('markets.trading.account_currency', 'USD');
        $leverage = (int) config('markets.trading.default_leverage', 100);

        return DB::transaction(function () use ($user, $balance, $currency, $leverage) {
            $account = TradingAccount::create([
                'user_id'   => $user->id,
                'login'     => $this->generateLogin(),
                'name'      => 'Demo Account',
                'type'      => 'demo',
                'currency'  => $currency,
                'leverage'  => $leverage,
                'balance'   => $balance,
                'is_active' => true,
            ]);

            Transaction::create([
                'trading_account_id' => $account->id,
                'type'               => 'deposit',
                'amount'             => $balance,
                'balance_after'      => $balance,
                'description'        => 'Initial demo deposit',
            ]);

            return $account;
        });
    }

    private function generateLogin(): string
    {
        do {
            $login = (string) random_int(5_000_000, 9_999_999);
        } while (TradingAccount::where('login', $login)->exists());

        return $login;
    }
}
