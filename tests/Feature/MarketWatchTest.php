<?php

namespace Tests\Feature;

use App\Models\Tick;
use App\Models\User;
use App\Services\MarketData\QuoteService;
use App\Services\Trading\AccountProvisioner;
use Database\Seeders\InstrumentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketWatchTest extends TestCase
{
    use RefreshDatabase;

    private function setupMarket(): User
    {
        $this->seed(InstrumentSeeder::class);
        app(QuoteService::class)->refresh();

        $user = User::create([
            'name' => 'MW Trader', 'email' => 'mw@example.com', 'password' => bcrypt('secret'),
        ]);
        app(AccountProvisioner::class)->createDemoAccount($user);

        return $user;
    }

    public function test_quotes_include_daily_change(): void
    {
        $user = $this->setupMarket();

        $response = $this->actingAs($user)->getJson('/api/quotes');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['symbol', 'bid', 'ask', 'spread', 'daily_change', 'category']]]);
    }

    public function test_refresh_records_tick_history_and_day_open(): void
    {
        $this->seed(InstrumentSeeder::class);
        $service = app(QuoteService::class);

        $service->refresh();
        $service->refresh();

        // Two refreshes => two ticks per instrument.
        $this->assertGreaterThanOrEqual(2, Tick::count() / 25);
        $this->assertDatabaseMissing('quotes', ['day_open' => null]);
    }

    public function test_specification_returns_contract_and_margin_details(): void
    {
        $user = $this->setupMarket();

        $response = $this->actingAs($user)->getJson('/api/instruments/EURUSD/specification');

        $response->assertOk()
            ->assertJsonPath('data.symbol', 'EURUSD')
            ->assertJsonPath('data.contract_size', 100000)
            ->assertJsonStructure(['data' => [
                'swap_long', 'swap_short', 'margin_per_lot', 'margin_currency', 'leverage', 'stops_level',
            ]]);

        $this->assertGreaterThan(0, $response->json('data.margin_per_lot'));
    }

    public function test_ticks_endpoint_returns_recent_history(): void
    {
        $user = $this->setupMarket();
        app(QuoteService::class)->refresh();
        app(QuoteService::class)->refresh();

        $response = $this->actingAs($user)->getJson('/api/instruments/EURUSD/ticks?limit=50');

        $response->assertOk()
            ->assertJsonPath('data.symbol', 'EURUSD')
            ->assertJsonStructure(['data' => ['symbol', 'digits', 'ticks' => [['bid', 'ask', 'mid', 'tick_at']]]]);
    }

    public function test_specification_404_for_unknown_symbol(): void
    {
        $user = $this->setupMarket();

        $this->actingAs($user)->getJson('/api/instruments/NOPE99/specification')->assertNotFound();
    }
}
