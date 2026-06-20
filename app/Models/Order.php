<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $fillable = [
        'ticket', 'trading_account_id', 'instrument_id', 'type', 'volume', 'price',
        'stop_loss', 'take_profit', 'status', 'position_id', 'expires_at', 'placed_at', 'filled_at',
    ];

    protected function casts(): array
    {
        return [
            'volume'      => 'float',
            'price'       => 'float',
            'stop_loss'   => 'float',
            'take_profit' => 'float',
            'expires_at'  => 'datetime',
            'placed_at'   => 'datetime',
            'filled_at'   => 'datetime',
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

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /** Market side (buy/sell) this pending order resolves to when filled. */
    public function side(): string
    {
        return str_starts_with($this->type, 'buy') ? 'buy' : 'sell';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
