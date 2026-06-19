<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Instrument extends Model
{
    protected $fillable = [
        'symbol', 'base_currency', 'quote_currency', 'description',
        'digits', 'pip_size', 'contract_size',
        'min_volume', 'max_volume', 'volume_step',
        'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'pip_size'      => 'float',
            'contract_size' => 'integer',
            'digits'        => 'integer',
            'min_volume'    => 'float',
            'max_volume'    => 'float',
            'volume_step'   => 'float',
            'is_active'     => 'boolean',
            'sort_order'    => 'integer',
        ];
    }

    public function quote(): HasOne
    {
        return $this->hasOne(Quote::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    /**
     * Monetary value of a single pip for the given volume (in quote currency).
     */
    public function pipValue(float $volume): float
    {
        return $this->pip_size * $this->contract_size * $volume;
    }
}
