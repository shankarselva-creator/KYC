<?php

namespace App\Services\Trading;

use App\Models\JournalEntry;
use App\Models\TradingAccount;

/**
 * Persists trade/order/funding events to a per-account journal so the log
 * survives reloads and captures server-side engine actions (pending fills,
 * SL/TP auto-closes) the browser never sees directly.
 */
class JournalService
{
    public function log(TradingAccount $account, string $message, string $level = 'info', string $category = 'trade'): void
    {
        JournalEntry::create([
            'trading_account_id' => $account->id,
            'level'              => $level,
            'category'           => $category,
            'message'            => $message,
        ]);
    }
}
