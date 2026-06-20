<?php

namespace App\Services\Trading;

use App\Models\TradingAccount;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FundingService
{
    public function __construct(private readonly TradingService $trading)
    {
    }

    public function deposit(TradingAccount $account, float $amount): Transaction
    {
        $this->assertAmount($amount);

        return $this->record($account, 'deposit', $amount, 'Deposit');
    }

    public function withdraw(TradingAccount $account, float $amount): Transaction
    {
        $this->assertAmount($amount);

        // Cannot withdraw more than free margin (funds not tied up in open positions).
        $free = $this->trading->accountMetrics($account)['free_margin'];
        if ($amount > $free) {
            throw new RuntimeException('Withdrawal exceeds free margin ('.number_format($free, 2).' '.$account->currency.').');
        }

        return $this->record($account, 'withdrawal', -$amount, 'Withdrawal');
    }

    private function record(TradingAccount $account, string $type, float $signedAmount, string $label): Transaction
    {
        return DB::transaction(function () use ($account, $type, $signedAmount, $label) {
            $newBalance = round($account->balance + $signedAmount, 2);
            $account->update(['balance' => $newBalance]);

            return Transaction::create([
                'trading_account_id' => $account->id,
                'type'               => $type,
                'amount'             => $signedAmount,
                'balance_after'      => $newBalance,
                'description'        => $label,
            ]);
        });
    }

    private function assertAmount(float $amount): void
    {
        if ($amount <= 0) {
            throw new RuntimeException('Amount must be greater than zero.');
        }
    }
}
