<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Candle extends Model
{
    protected $fillable = [
        'instrument_id', 'timeframe', 'opened_at', 'open', 'high', 'low', 'close', 'volume',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'open'      => 'float',
            'high'      => 'float',
            'low'       => 'float',
            'close'     => 'float',
            'volume'    => 'integer',
        ];
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }
}
