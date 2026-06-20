<?php

namespace Database\Seeders;

use App\Models\Instrument;
use Illuminate\Database\Seeder;

class InstrumentSeeder extends Seeder
{
    public function run(): void
    {
        // [symbol, base, quote, description, digits, pip, contract, category, swapL, swapS]
        $instruments = [
            // Majors
            ['EURUSD', 'EUR', 'USD', 'Euro vs US Dollar',              5, 0.0001, 100000, 'Majors', -0.79, -0.21],
            ['GBPUSD', 'GBP', 'USD', 'Great Britain Pound vs US Dollar', 5, 0.0001, 100000, 'Majors', -1.10, -0.35],
            ['USDJPY', 'USD', 'JPY', 'US Dollar vs Japanese Yen',      3, 0.01,   100000, 'Majors',  0.45, -1.20],
            ['USDCHF', 'USD', 'CHF', 'US Dollar vs Swiss Franc',       5, 0.0001, 100000, 'Majors',  0.30, -0.80],
            ['AUDUSD', 'AUD', 'USD', 'Australian Dollar vs US Dollar', 5, 0.0001, 100000, 'Majors', -0.55, -0.18],
            ['USDCAD', 'USD', 'CAD', 'US Dollar vs Canadian Dollar',   5, 0.0001, 100000, 'Majors',  0.20, -0.70],
            ['NZDUSD', 'NZD', 'USD', 'New Zealand Dollar vs US Dollar', 5, 0.0001, 100000, 'Majors', -0.50, -0.20],
            // Minors / crosses
            ['EURJPY', 'EUR', 'JPY', 'Euro vs Japanese Yen',          3, 0.01,   100000, 'Minors',  0.10, -1.05],
            ['EURGBP', 'EUR', 'GBP', 'Euro vs Great Britain Pound',   5, 0.0001, 100000, 'Minors', -0.45, -0.30],
            ['GBPJPY', 'GBP', 'JPY', 'Great Britain Pound vs Japanese Yen', 3, 0.01, 100000, 'Minors', 0.15, -1.30],
            ['AUDJPY', 'AUD', 'JPY', 'Australian Dollar vs Japanese Yen', 3, 0.01, 100000, 'Minors', 0.25, -0.95],
            ['AUDCAD', 'AUD', 'CAD', 'Australian Dollar vs Canadian Dollar', 5, 0.0001, 100000, 'Minors', -0.40, -0.25],
            ['AUDCHF', 'AUD', 'CHF', 'Australian Dollar vs Swiss Franc', 5, 0.0001, 100000, 'Minors', -0.30, -0.35],
            ['AUDNZD', 'AUD', 'NZD', 'Australian Dollar vs New Zealand Dollar', 5, 0.0001, 100000, 'Minors', -0.35, -0.30],
            ['CADJPY', 'CAD', 'JPY', 'Canadian Dollar vs Japanese Yen', 3, 0.01, 100000, 'Minors', 0.20, -0.90],
            ['CHFJPY', 'CHF', 'JPY', 'Swiss Franc vs Japanese Yen',    3, 0.01, 100000, 'Minors', -0.20, -0.85],
            ['EURAUD', 'EUR', 'AUD', 'Euro vs Australian Dollar',      5, 0.0001, 100000, 'Minors', -0.60, -0.20],
            ['EURCAD', 'EUR', 'CAD', 'Euro vs Canadian Dollar',        5, 0.0001, 100000, 'Minors', -0.55, -0.25],
            ['GBPAUD', 'GBP', 'AUD', 'Great Britain Pound vs Australian Dollar', 5, 0.0001, 100000, 'Minors', -0.70, -0.30],
            // Exotics
            ['USDSEK', 'USD', 'SEK', 'US Dollar vs Swedish Krona',     5, 0.0001, 100000, 'Exotics', 0.50, -2.10],
            ['USDNOK', 'USD', 'NOK', 'US Dollar vs Norwegian Krone',   5, 0.0001, 100000, 'Exotics', 0.45, -1.95],
            ['USDZAR', 'USD', 'ZAR', 'US Dollar vs South African Rand', 5, 0.0001, 100000, 'Exotics', 1.20, -5.50],
            ['USDMXN', 'USD', 'MXN', 'US Dollar vs Mexican Peso',      5, 0.0001, 100000, 'Exotics', 0.90, -4.80],
            // Metals
            ['XAUUSD', 'XAU', 'USD', 'Gold vs US Dollar',             2, 0.01,   100,    'Metals', -3.50, -2.00],
            ['XAGUSD', 'XAG', 'USD', 'Silver vs US Dollar',           3, 0.001,  5000,   'Metals', -1.50, -1.00],
        ];

        foreach ($instruments as $i => [$symbol, $base, $quote, $desc, $digits, $pip, $contract, $cat, $swapL, $swapS]) {
            Instrument::updateOrCreate(
                ['symbol' => $symbol],
                [
                    'base_currency'  => $base,
                    'quote_currency' => $quote,
                    'description'    => $desc,
                    'digits'         => $digits,
                    'pip_size'       => $pip,
                    'contract_size'  => $contract,
                    'min_volume'     => 0.01,
                    'max_volume'     => 100.00,
                    'volume_step'    => 0.01,
                    'swap_long'      => $swapL,
                    'swap_short'     => $swapS,
                    'stops_level'    => 0,
                    'category'       => $cat,
                    'is_active'      => true,
                    'sort_order'     => $i,
                ],
            );
        }
    }
}
