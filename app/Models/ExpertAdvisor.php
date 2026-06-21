<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpertAdvisor extends Model
{
    protected $fillable = [
        'trading_account_id', 'instrument_id', 'name', 'strategy', 'timeframe',
        'volume', 'params', 'state', 'magic', 'is_active', 'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'volume'      => 'float',
            'params'      => 'array',
            'state'       => 'array',
            'magic'       => 'integer',
            'is_active'   => 'boolean',
            'last_run_at' => 'datetime',
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

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }
}
