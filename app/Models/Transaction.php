<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'trading_account_id', 'position_id', 'type', 'amount', 'balance_after', 'description',
    ];

    protected function casts(): array
    {
        return [
            'amount'        => 'float',
            'balance_after' => 'float',
        ];
    }

    public function tradingAccount(): BelongsTo
    {
        return $this->belongsTo(TradingAccount::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }
}
