<?php

namespace Tests\Feature;

use App\Models\Candle;
use App\Models\Instrument;
use App\Models\Quote;
use App\Models\User;
use App\Services\Experts\Backtester;
use App\Services\Experts\Strategies\MovingAverageCrossStrategy;
use App\Services\MarketData\CandleService;
use App\Services\Trading\AccountProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BacktestTest extends TestCase
{
    use RefreshDatabase;

    private function instrument(): Instrument
    {
        $ins = Instrument::create([
            'symbol' => 'EURUSD', 'base_currency' => 'EUR', 'quote_currency' => 'USD',
            'description' => 'x', 'digits' => 5, 'pip_size' => 0.0001, 'contract_size' => 100000,
            'min_volume' => 0.01, 'max_volume' => 100, 'volume_step' => 0.01, 'is_active' => true,
        ]);
        Quote::create(['instrument_id' => $ins->id, 'bid' => 1.2, 'ask' => 1.2001, 'day_open' => 1.2, 'day_open_date' => today()]);

        return $ins;
    }

    /** @param array<int,float> $closes */
    private function seedBars(Instrument $ins, array $closes): void
    {
        $t = Carbon::now()->subMinutes(count($closes) * 5);
        foreach ($closes as $c) {
            Candle::create([
                'instrument_id' => $ins->id, 'timeframe' => 'M5', 'opened_at' => $t->copy(),
                'open' => $c, 'high' => $c + 0.0003, 'low' => $c - 0.0003, 'close' => $c, 'volume' => 100,
            ]);
            $t->addMinutes(5);
        }
    }

    public function test_backtest_reports_a_profitable_uptrend(): void
    {
        $ins = $this->instrument();

        // Steady uptrend → MA cross goes long early and rides it up.
        $closes = [];
        for ($i = 0; $i < 40; $i++) {
            $closes[] = 1.20000 - $i * 0.0010; // downtrend first
        }
        for ($i = 0; $i < 100; $i++) {
            $closes[] = 1.16100 + $i * 0.0010; // then uptrend → bullish MA cross
        }
        $this->seedBars($ins, $closes);

        $bars = app(CandleService::class)->recent($ins, 'M5', 500);
        $report = app(Backtester::class)->run(
            new MovingAverageCrossStrategy(), $ins, ['fast' => 5, 'slow' => 20], 0.10, null, null, 1, $bars,
        );

        $this->assertGreaterThan(0, $report['trades']);
        $this->assertGreaterThan(0, $report['net_profit']); // long in an uptrend = profit
        $this->assertArrayHasKey('profit_factor', $report);
        $this->assertArrayHasKey('max_drawdown', $report);
    }

    public function test_backtest_endpoint(): void
    {
        $ins = $this->instrument();
        $closes = [];
        for ($i = 0; $i < 40; $i++) {
            $closes[] = 1.20000 - $i * 0.0010; // downtrend first
        }
        for ($i = 0; $i < 100; $i++) {
            $closes[] = 1.16100 + $i * 0.0010; // then uptrend → bullish MA cross
        }
        $this->seedBars($ins, $closes);

        $user = User::create(['name' => 'B', 'email' => 'b@example.com', 'password' => bcrypt('x')]);
        app(AccountProvisioner::class)->createDemoAccount($user);

        $response = $this->actingAs($user)->postJson('/api/experts/backtest', [
            'strategy' => 'ma_cross', 'symbol' => 'EURUSD', 'timeframe' => 'M5',
            'volume' => 0.10, 'params' => ['fast' => 5, 'slow' => 20],
        ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => [
                'symbol', 'currency', 'trades', 'wins', 'losses', 'win_rate',
                'net_profit', 'profit_factor', 'max_drawdown', 'bars',
            ]]);
    }

    public function test_backtest_requires_history(): void
    {
        $this->instrument();
        $user = User::create(['name' => 'B', 'email' => 'b2@example.com', 'password' => bcrypt('x')]);
        app(AccountProvisioner::class)->createDemoAccount($user);

        $this->actingAs($user)->postJson('/api/experts/backtest', [
            'strategy' => 'ma_cross', 'symbol' => 'EURUSD', 'timeframe' => 'M5', 'volume' => 0.10,
        ])->assertStatus(422)->assertJsonPath('error.code', 'NO_HISTORY');
    }
}
