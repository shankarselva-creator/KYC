<?php

namespace Database\Seeders;

use App\Models\Instrument;
use Illuminate\Database\Seeder;

class InstrumentSeeder extends Seeder
{
    public function run(): void
    {
        $instruments = [
            ['EURUSD', 'EUR', 'USD', 'Euro vs US Dollar',        5, 0.0001, 100000],
            ['GBPUSD', 'GBP', 'USD', 'Great Britain Pound vs US Dollar', 5, 0.0001, 100000],
            ['USDJPY', 'USD', 'JPY', 'US Dollar vs Japanese Yen', 3, 0.01,   100000],
            ['USDCHF', 'USD', 'CHF', 'US Dollar vs Swiss Franc',  5, 0.0001, 100000],
            ['AUDUSD', 'AUD', 'USD', 'Australian Dollar vs US Dollar', 5, 0.0001, 100000],
            ['USDCAD', 'USD', 'CAD', 'US Dollar vs Canadian Dollar', 5, 0.0001, 100000],
            ['NZDUSD', 'NZD', 'USD', 'New Zealand Dollar vs US Dollar', 5, 0.0001, 100000],
            ['EURJPY', 'EUR', 'JPY', 'Euro vs Japanese Yen',      3, 0.01,   100000],
            ['EURGBP', 'EUR', 'GBP', 'Euro vs Great Britain Pound', 5, 0.0001, 100000],
            ['XAUUSD', 'XAU', 'USD', 'Gold vs US Dollar',         2, 0.01,   100],
        ];

        foreach ($instruments as $i => [$symbol, $base, $quote, $desc, $digits, $pip, $contract]) {
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
                    'is_active'      => true,
                    'sort_order'     => $i,
                ],
            );
        }
    }
}
