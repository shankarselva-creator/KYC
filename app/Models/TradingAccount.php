<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TradingAccount extends Model
{
    protected $fillable = [
        'user_id', 'login', 'name', 'type', 'currency', 'leverage', 'balance', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'leverage'  => 'integer',
            'balance'   => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    public function openPositions(): HasMany
    {
        return $this->positions()->where('status', 'open');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
