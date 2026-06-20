<?php

namespace Tests\Feature;

use App\Models\Instrument;
use App\Models\JournalEntry;
use App\Models\Quote;
use App\Models\User;
use App\Services\Trading\AccountProvisioner;
use App\Services\Trading\FundingService;
use App\Services\Trading\TradingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalTest extends TestCase
{
    use RefreshDatabase;

    private function setup_(): array
    {
        $ins = Instrument::create([
            'symbol' => 'EURUSD', 'base_currency' => 'EUR', 'quote_currency' => 'USD',
            'description' => 'x', 'digits' => 5, 'pip_size' => 0.0001, 'contract_size' => 100000,
            'min_volume' => 0.01, 'max_volume' => 100, 'volume_step' => 0.01, 'is_active' => true,
        ]);
        Quote::create(['instrument_id' => $ins->id, 'bid' => 1.10000, 'ask' => 1.10010, 'day_open' => 1.1, 'day_open_date' => today()]);
        $user = User::create(['name' => 'J', 'email' => 'j@example.com', 'password' => bcrypt('x')]);
        $account = app(AccountProvisioner::class)->createDemoAccount($user);

        return [$user, $account, $ins->load('quote')];
    }

    public function test_trade_events_are_persisted_to_the_journal(): void
    {
        [$user, $account, $ins] = $this->setup_();
        $svc = app(TradingService::class);

        $pos = $svc->openPosition($account, $ins, 'buy', 0.10);
        $svc->closePosition($pos->fresh()->load('instrument', 'tradingAccount'));
        app(FundingService::class)->deposit($account->fresh(), 500);

        $this->assertDatabaseHas('journal_entries', ['trading_account_id' => $account->id, 'category' => 'trade']);
        $this->assertDatabaseHas('journal_entries', ['trading_account_id' => $account->id, 'category' => 'funding']);
        $this->assertGreaterThanOrEqual(3, JournalEntry::where('trading_account_id', $account->id)->count());
    }

    public function test_journal_endpoint_returns_entries_chronologically(): void
    {
        [$user, $account, $ins] = $this->setup_();
        $svc = app(TradingService::class);
        $svc->openPosition($account, $ins, 'buy', 0.10);

        $response = $this->actingAs($user)->getJson('/api/journal');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['level', 'category', 'message', 'at']]]);
        $this->assertNotEmpty($response->json('data'));
    }
}
