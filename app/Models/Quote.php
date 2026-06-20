<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quote extends Model
{
    protected $fillable = [
        'instrument_id', 'bid', 'ask', 'day_open', 'day_open_date', 'quoted_at',
    ];

    protected function casts(): array
    {
        return [
            'bid'           => 'float',
            'ask'           => 'float',
            'day_open'      => 'float',
            'day_open_date' => 'date',
            'quoted_at'     => 'datetime',
        ];
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function getMidAttribute(): float
    {
        return ($this->bid + $this->ask) / 2;
    }

    public function getSpreadAttribute(): float
    {
        return round($this->ask - $this->bid, 8);
    }

    /**
     * Percentage change of the mid-price versus the day's opening reference.
     */
    public function getDailyChangeAttribute(): ?float
    {
        if (! $this->day_open || $this->day_open <= 0) {
            return null;
        }

        return round(($this->mid - $this->day_open) / $this->day_open * 100, 2);
    }
}
