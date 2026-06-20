<?php

namespace Tests\Feature;

use App\Models\Position;
use App\Models\User;
use App\Services\MarketData\QuoteService;
use App\Services\Trading\AccountProvisioner;
use Database\Seeders\InstrumentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TradingApiTest extends TestCase
{
    use RefreshDatabase;

    private function setupMarket(): User
    {
        $this->seed(InstrumentSeeder::class);
        app(QuoteService::class)->refresh();

        $user = User::create([
            'name' => 'API Trader', 'email' => 'api@example.com', 'password' => bcrypt('secret'),
        ]);
        app(AccountProvisioner::class)->createDemoAccount($user);

        return $user;
    }

    public function test_registration_provisions_a_demo_account(): void
    {
        $response = $this->post('/register', [
            'name' => 'New Trader',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('terminal'));
        $user = User::where('email', 'new@example.com')->first();
        $this->assertNotNull($user->primaryTradingAccount());
        $this->assertEqualsWithDelta(10000, $user->primaryTradingAccount()->balance, 0.01);
    }

    public function test_quotes_endpoint_returns_instruments(): void
    {
        $user = $this->setupMarket();

        $response = $this->actingAs($user)->getJson('/api/quotes');

        $response->assertOk()
            ->assertJsonPath('error', null)
            ->assertJsonStructure(['data' => [['symbol', 'bid', 'ask', 'spread']]]);
        $this->assertCount(25, $response->json('data'));
    }

    public function test_can_open_and_close_a_position(): void
    {
        $user = $this->setupMarket();

        $open = $this->actingAs($user)->postJson('/api/orders', [
            'symbol' => 'EURUSD',
            'type' => 'buy',
            'volume' => 0.10,
        ]);

        $open->assertOk()->assertJsonPath('data.symbol', 'EURUSD');
        $ticket = $open->json('data.ticket');
        $this->assertDatabaseHas('positions', ['ticket' => $ticket, 'status' => 'open']);

        $position = Position::where('ticket', $ticket)->first();
        $close = $this->actingAs($user)->postJson("/api/positions/{$position->id}/close");

        $close->assertOk()->assertJsonPath('data.status', 'closed');
        $this->assertDatabaseHas('positions', ['ticket' => $ticket, 'status' => 'closed']);
    }

    public function test_order_validation_rejects_unknown_symbol(): void
    {
        $user = $this->setupMarket();

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'symbol' => 'FAKE99',
            'type' => 'buy',
            'volume' => 0.10,
        ]);

        $response->assertStatus(422);
    }

    public function test_cannot_close_another_users_position(): void
    {
        $owner = $this->setupMarket();
        $position = app(\App\Services\Trading\TradingService::class)->openPosition(
            $owner->primaryTradingAccount(),
            \App\Models\Instrument::where('symbol', 'EURUSD')->first(),
            'buy',
            0.10,
        );

        $intruder = User::create(['name' => 'X', 'email' => 'x@example.com', 'password' => bcrypt('secret')]);
        app(AccountProvisioner::class)->createDemoAccount($intruder);

        $response = $this->actingAs($intruder)->postJson("/api/positions/{$position->id}/close");

        $response->assertStatus(403);
    }
}
