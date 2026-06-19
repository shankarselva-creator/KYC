<?php

namespace Database\Seeders;

use App\Models\User;
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
