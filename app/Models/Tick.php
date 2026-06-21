<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tick extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'instrument_id', 'bid', 'ask', 'tick_at',
    ];

    protected function casts(): array
    {
        return [
            'bid'     => 'float',
            'ask'     => 'float',
            'tick_at' => 'datetime',
        ];
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }
}
