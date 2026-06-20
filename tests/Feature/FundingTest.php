<?php

namespace Tests\Feature;

use App\Models\Instrument;
use App\Models\Quote;
use App\Models\User;
use App\Services\Trading\AccountProvisioner;
use App\Services\Trading\FundingService;
use App\Services\Trading\TradingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FundingTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $user = User::create(['name' => 'F', 'email' => 'f@example.com', 'password' => bcrypt('x')]);
        app(AccountProvisioner::class)->createDemoAccount($user);

        return $user;
    }

    public function test_deposit_increases_balance_and_logs_ledger(): void
    {
        $user = $this->user();

        $response = $this->actingAs($user)->postJson('/api/account/deposit', ['amount' => 2500]);

        $response->assertOk()->assertJsonPath('data.balance_after', 12500);
        $this->assertDatabaseHas('transactions', ['type' => 'deposit', 'amount' => 2500, 'balance_after' => 12500]);
    }

    public function test_withdraw_decreases_balance(): void
    {
        $user = $this->user();

        $response = $this->actingAs($user)->postJson('/api/account/withdraw', ['amount' => 1000]);

        $response->assertOk()->assertJsonPath('data.balance_after', 9000);
        $this->assertEqualsWithDelta(9000, $user->primaryTradingAccount()->balance, 0.01);
    }

    public function test_withdraw_cannot_exceed_free_margin(): void
    {
        $user = $this->user();

        $response = $this->actingAs($user)->postJson('/api/account/withdraw', ['amount' => 999999]);

        $response->assertStatus(422)->assertJsonPath('error.code', 'FUNDING_FAILED');
    }

    public function test_withdraw_blocked_when_margin_is_tied_up(): void
    {
        $user = $this->user();
        $account = $user->primaryTradingAccount();

        $ins = Instrument::create([
            'symbol' => 'EURUSD', 'base_currency' => 'EUR', 'quote_currency' => 'USD',
            'description' => 'x', 'digits' => 5, 'pip_size' => 0.0001, 'contract_size' => 100000,
            'min_volume' => 0.01, 'max_volume' => 100, 'volume_step' => 0.01, 'is_active' => true,
        ]);
        Quote::create(['instrument_id' => $ins->id, 'bid' => 1.10000, 'ask' => 1.10010, 'day_open' => 1.1, 'day_open_date' => today()]);
        // Use ~half the balance as margin (1 lot @ 1.1 / 100 leverage = ~1100).
        app(TradingService::class)->openPosition($account->fresh(), $ins->load('quote'), 'buy', 5.0);

        // Free margin is now well under 10000; a 9500 withdrawal must be rejected.
        $this->expectException(\RuntimeException::class);
        app(FundingService::class)->withdraw($account->fresh(), 9500);
    }

    public function test_funding_requires_positive_amount(): void
    {
        $user = $this->user();

        $this->actingAs($user)->postJson('/api/account/deposit', ['amount' => -5])->assertStatus(422);
    }
}
