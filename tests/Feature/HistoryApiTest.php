<?php

namespace Tests\Feature;

use App\Models\Instrument;
use App\Models\Quote;
use App\Models\User;
use App\Services\Trading\AccountProvisioner;
use App\Services\Trading\TradingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoryApiTest extends TestCase
{
    use RefreshDatabase;

    private function market(): Instrument
    {
        $ins = Instrument::create([
            'symbol' => 'EURUSD', 'base_currency' => 'EUR', 'quote_currency' => 'USD',
            'description' => 'Euro vs US Dollar', 'digits' => 5, 'pip_size' => 0.0001,
            'contract_size' => 100000, 'min_volume' => 0.01, 'max_volume' => 100, 'volume_step' => 0.01,
            'is_active' => true,
        ]);
        Quote::create(['instrument_id' => $ins->id, 'bid' => 1.10000, 'ask' => 1.10010, 'day_open' => 1.10005, 'day_open_date' => today()]);

        return $ins->load('quote');
    }

    public function test_history_reports_closed_trades_and_summary(): void
    {
        $ins = $this->market();
        $user = User::create(['name' => 'H', 'email' => 'h@example.com', 'password' => bcrypt('x')]);
        $account = app(AccountProvisioner::class)->createDemoAccount($user);
        $svc = app(TradingService::class);

        // One winning, one losing closed trade.
        $win = $svc->openPosition($account, $ins, 'buy', 0.10);
        $ins->quote()->update(['bid' => 1.10500, 'ask' => 1.10510]);
        $ins->refresh()->load('quote');
        $svc->closePosition($win->fresh()->load('instrument', 'tradingAccount'));

        $loss = $svc->openPosition($account, $ins, 'buy', 0.10);
        $ins->quote()->update(['bid' => 1.09000, 'ask' => 1.09010]);
        $ins->refresh()->load('quote');
        $svc->closePosition($loss->fresh()->load('instrument', 'tradingAccount'));

        $response = $this->actingAs($user)->getJson('/api/history');

        $response->assertOk()
            ->assertJsonPath('data.summary.trades', 2)
            ->assertJsonPath('data.summary.wins', 1)
            ->assertJsonPath('data.summary.losses', 1)
            ->assertJsonPath('data.summary.win_rate', 50)
            ->assertJsonPath('data.summary.deposits', 10000)
            ->assertJsonStructure(['data' => [
                'summary' => ['closed_pnl', 'deposits', 'withdrawals', 'trades', 'wins', 'losses', 'win_rate', 'currency'],
                'positions' => [['ticket', 'symbol', 'side', 'profit', 'closed_at']],
                'transactions' => [['type', 'amount', 'balance_after']],
            ]]);

        $this->assertCount(2, $response->json('data.positions'));
    }

    public function test_history_requires_authentication(): void
    {
        $this->getJson('/api/history')->assertUnauthorized();
    }
}
