<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quote extends Model
{
    protected $fillable = [
        'instrument_id', 'bid', 'ask', 'quoted_at',
    ];

    protected function casts(): array
    {
        return [
            'bid'       => 'float',
            'ask'       => 'float',
            'quoted_at' => 'datetime',
        ];
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function getSpreadAttribute(): float
    {
        return round($this->ask - $this->bid, 8);
    }
}
