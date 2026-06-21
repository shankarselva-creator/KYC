<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Position extends Model
{
    protected $fillable = [
        'ticket', 'trading_account_id', 'instrument_id', 'expert_advisor_id', 'magic',
        'side', 'volume', 'open_price', 'close_price', 'stop_loss', 'take_profit',
        'commission', 'swap', 'profit', 'status', 'opened_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'volume'       => 'float',
            'open_price'   => 'float',
            'close_price'  => 'float',
            'stop_loss'    => 'float',
            'take_profit'  => 'float',
            'commission'   => 'float',
            'swap'         => 'float',
            'profit'       => 'float',
            'opened_at'    => 'datetime',
            'closed_at'    => 'datetime',
        ];
    }

    public function tradingAccount(): BelongsTo
    {
        return $this->belongsTo(TradingAccount::class);
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
