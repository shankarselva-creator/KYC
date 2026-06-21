<?php

namespace Tests\Feature;

use App\Models\Instrument;
use App\Models\Order;
use App\Models\Position;
use App\Models\Quote;
use App\Models\TradingAccount;
use App\Models\User;
use App\Services\Trading\TradingEngine;
use App\Services\Trading\TradingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PendingOrderTest extends TestCase
{
    use RefreshDatabase;

    private Instrument $instrument;
    private TradingAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instrument = Instrument::create([
            'symbol' => 'EURUSD', 'base_currency' => 'EUR', 'quote_currency' => 'USD',
            'description' => 'Euro vs US Dollar', 'digits' => 5, 'pip_size' => 0.0001,
            'contract_size' => 100000, 'min_volume' => 0.01, 'max_volume' => 100.00, 'volume_step' => 0.01,
            'is_active' => true,
        ]);
        $this->setPrice(1.10000, 1.10010);

        $user = User::create(['name' => 'T', 'email' => 't@example.com', 'password' => 'x']);
        $this->account = TradingAccount::create([
            'user_id' => $user->id, 'login' => '5000001', 'type' => 'demo',
            'currency' => 'USD', 'leverage' => 100, 'balance' => 10000, 'is_active' => true,
        ]);
    }

    private function setPrice(float $bid, float $ask): void
    {
        Quote::updateOrCreate(
            ['instrument_id' => $this->instrument->id],
            ['bid' => $bid, 'ask' => $ask, 'day_open' => ($bid + $ask) / 2, 'day_open_date' => today()],
        );
        $this->instrument->refresh()->load('quote');
    }

    private function svc(): TradingService
    {
        return app(TradingService::class);
    }

    private function engine(): TradingEngine
    {
        return app(TradingEngine::class);
    }

    public function test_buy_limit_must_be_below_ask(): void
    {
        $this->expectExceptionMessage('below the current Ask');
        $this->svc()->placePendingOrder($this->account, $this->instrument, 'buy_limit', 0.10, 1.20000);
    }

    public function test_buy_stop_fills_when_price_rises(): void
    {
        $order = $this->svc()->placePendingOrder($this->account, $this->instrument, 'buy_stop', 0.10, 1.10100);
        $this->assertSame('pending', $order->status);

        // Not triggered yet.
        $this->engine()->tick();
        $this->assertSame('pending', $order->fresh()->status);

        // Price rises through the trigger.
        $this->setPrice(1.10100, 1.10110);
        $result = $this->engine()->tick();

        $this->assertSame(1, $result['filled']);
        $order->refresh();
        $this->assertSame('filled', $order->status);
        $this->assertNotNull($order->position_id);

        $position = Position::find($order->position_id);
        $this->assertSame('buy', $position->side);
        $this->assertEqualsWithDelta(1.10100, $position->open_price, 0.00001); // filled at order price
    }

    public function test_sell_limit_fills_when_price_rises_to_level(): void
    {
        $order = $this->svc()->placePendingOrder($this->account, $this->instrument, 'sell_limit', 0.10, 1.10100);

        $this->setPrice(1.10100, 1.10110); // bid >= 1.10100
        $result = $this->engine()->tick();

        $this->assertSame(1, $result['filled']);
        $this->assertSame('sell', Position::find($order->fresh()->position_id)->side);
    }

    public function test_take_profit_auto_closes_buy_position(): void
    {
        $position = $this->svc()->openPosition($this->account, $this->instrument, 'buy', 0.10, [
            'take_profit' => 1.10500,
        ]);

        // Price climbs to the TP; buy closes at bid.
        $this->setPrice(1.10500, 1.10510);
        $result = $this->engine()->tick();

        $this->assertSame(1, $result['closed']);
        $this->assertSame('closed', $position->fresh()->status);
        $this->assertGreaterThan(0, $position->fresh()->profit);
    }

    public function test_stop_loss_auto_closes_sell_position(): void
    {
        $position = $this->svc()->openPosition($this->account, $this->instrument, 'sell', 0.10, [
            'stop_loss' => 1.10200,
        ]);

        // Price rises against the sell; sell closes at ask >= SL.
        $this->setPrice(1.10200, 1.10210);
        $result = $this->engine()->tick();

        $this->assertSame(1, $result['closed']);
        $this->assertSame('closed', $position->fresh()->status);
    }

    public function test_expired_pending_order_is_marked_expired(): void
    {
        $order = $this->svc()->placePendingOrder($this->account, $this->instrument, 'buy_limit', 0.10, 1.09000, [
            'expires_at' => Carbon::now()->subMinute(),
        ]);

        $result = $this->engine()->tick();

        $this->assertSame(1, $result['expired']);
        $this->assertSame('expired', $order->fresh()->status);
    }

    public function test_modify_position_sl_tp(): void
    {
        $position = $this->svc()->openPosition($this->account, $this->instrument, 'buy', 0.10);

        $updated = $this->svc()->modifyPosition($position, 1.09000, 1.12000);

        $this->assertEqualsWithDelta(1.09000, $updated->stop_loss, 0.00001);
        $this->assertEqualsWithDelta(1.12000, $updated->take_profit, 0.00001);
    }

    public function test_invalid_sl_for_buy_is_rejected(): void
    {
        $position = $this->svc()->openPosition($this->account, $this->instrument, 'buy', 0.10);

        $this->expectExceptionMessage('Stop Loss for a buy must be below');
        $this->svc()->modifyPosition($position, 1.20000, null); // SL above bid
    }

    public function test_cancel_pending_order(): void
    {
        $order = $this->svc()->placePendingOrder($this->account, $this->instrument, 'buy_limit', 0.10, 1.09000);

        $this->svc()->cancelPendingOrder($order);

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(0, $this->engine()->tick()['filled']);
    }

    // ---- HTTP API ----

    public function test_api_places_pending_order(): void
    {
        $response = $this->actingAs($this->account->user)->postJson('/api/orders', [
            'type' => 'buy_limit', 'symbol' => 'EURUSD', 'volume' => 0.10, 'price' => 1.09000,
        ]);

        $response->assertOk()->assertJsonPath('data.kind', 'pending');
        $this->assertDatabaseHas('orders', ['ticket' => $response->json('data.ticket'), 'status' => 'pending']);
    }

    public function test_api_pending_requires_price(): void
    {
        $this->actingAs($this->account->user)->postJson('/api/orders', [
            'type' => 'buy_limit', 'symbol' => 'EURUSD', 'volume' => 0.10,
        ])->assertStatus(422);
    }

    public function test_api_lists_and_cancels_pending_orders(): void
    {
        $order = $this->svc()->placePendingOrder($this->account, $this->instrument, 'buy_limit', 0.10, 1.09000);

        $this->actingAs($this->account->user)->getJson('/api/orders')
            ->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->account->user)->postJson("/api/orders/{$order->id}/cancel")
            ->assertOk()->assertJsonPath('data.status', 'cancelled');
    }

    public function test_api_modifies_position_sltp(): void
    {
        $position = $this->svc()->openPosition($this->account, $this->instrument, 'buy', 0.10);

        $this->actingAs($this->account->user)->postJson("/api/positions/{$position->id}/modify", [
            'stop_loss' => 1.09000, 'take_profit' => 1.12000,
        ])->assertOk()->assertJsonPath('data.ticket', $position->ticket);

        $this->assertEqualsWithDelta(1.09000, $position->fresh()->stop_loss, 0.00001);
    }
}
