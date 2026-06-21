<?php

namespace App\Services\Trading;

/**
 * Converts amounts between currencies using a map of mid-prices.
 *
 * The price map is keyed by 6-letter pair symbol (e.g. "EURUSD") => mid price.
 * Conversions try a direct pair, then the inverse pair; if neither is available
 * the amount is returned unchanged (best-effort for the MVP).
 */
class CurrencyConverter
{
    /**
     * @param  array<string, float>  $prices  symbol => mid price
     */
    public function __construct(private readonly array $prices)
    {
    }

    public function convert(float $amount, string $from, string $to): float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return $amount;
        }

        $direct = $from . $to;
        if (isset($this->prices[$direct]) && $this->prices[$direct] > 0) {
            return $amount * $this->prices[$direct];
        }

        $inverse = $to . $from;
        if (isset($this->prices[$inverse]) && $this->prices[$inverse] > 0) {
            return $amount / $this->prices[$inverse];
        }

        // No conversion path available — assume parity as a fallback.
        return $amount;
    }
}
