<?php

namespace Database\Seeders;

use App\Models\Instrument;
use App\Models\User;
use App\Services\MarketData\CandleService;
use App\Services\MarketData\QuoteService;
use App\Services\Trading\AccountProvisioner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(InstrumentSeeder::class);

        // Populate an initial set of quotes so the terminal has prices on first load.
        app(QuoteService::class)->refresh();

        // Generate synthetic candle history for every instrument & timeframe.
        $candles = app(CandleService::class);
        foreach (Instrument::where('is_active', true)->with('quote')->get() as $instrument) {
            $mid = $instrument->quote ? ($instrument->quote->bid + $instrument->quote->ask) / 2 : 1.0;
            foreach (array_keys(CandleService::TIMEFRAMES) as $tf) {
                $candles->seedHistory($instrument, $tf, 200, $mid);
            }
        }

        $user = User::updateOrCreate(
            ['email' => 'trader@example.com'],
            [
                'name'     => 'Demo Trader',
                'password' => Hash::make('password'),
            ],
        );

        if ($user->tradingAccounts()->count() === 0) {
            app(AccountProvisioner::class)->createDemoAccount($user);
        }
    }
}
