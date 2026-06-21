<?php

namespace Tests\Feature;

use App\Models\Candle;
use App\Models\ExpertAdvisor;
use App\Models\Instrument;
use App\Models\Position;
use App\Models\Quote;
use App\Models\User;
use App\Services\Experts\ExpertAdvisorRunner;
use App\Services\Experts\Indicators;
use App\Services\Experts\Strategies\MovingAverageCrossStrategy;
use App\Services\Experts\Strategies\RsiReversionStrategy;
use App\Services\Experts\StrategyContext;
use App\Services\Trading\AccountProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ExpertAdvisorTest extends TestCase
{
    use RefreshDatabase;

    private function instrument(float $price = 1.20000): Instrument
    {
        $ins = Instrument::create([
            'symbol' => 'EURUSD', 'base_currency' => 'EUR', 'quote_currency' => 'USD',
            'description' => 'x', 'digits' => 5, 'pip_size' => 0.0001, 'contract_size' => 100000,
            'min_volume' => 0.01, 'max_volume' => 100, 'volume_step' => 0.01, 'is_active' => true,
        ]);
        Quote::create(['instrument_id' => $ins->id, 'bid' => $price, 'ask' => $price + 0.0001, 'day_open' => $price, 'day_open_date' => today()]);

        return $ins->load('quote');
    }

    /** @param array<int,float> $closes */
    private function seedCandles(Instrument $ins, array $closes, string $tf = 'M5'): void
    {
        $t = Carbon::now()->subMinutes(count($closes) * 5);
        foreach ($closes as $c) {
            Candle::create([
                'instrument_id' => $ins->id, 'timeframe' => $tf, 'opened_at' => $t->copy(),
                'open' => $c, 'high' => $c + 0.0002, 'low' => $c - 0.0002, 'close' => $c, 'volume' => 100,
            ]);
            $t->addMinutes(5);
        }
    }

    // ---- Strategy unit logic ----

    public function test_ma_cross_signals_buy_on_bullish_cross(): void
    {
        $closes = array_fill(0, 59, 1.10000);
        $closes[] = 1.20000; // sharp jump → fast crosses above slow on the last bar
        $ctx = new StrategyContext(new ExpertAdvisor(['strategy' => 'ma_cross']), ['fast' => 10, 'slow' => 30], $closes, [], [], []);

        $actions = (new MovingAverageCrossStrategy())->decide($ctx);

        $this->assertCount(1, $actions);
        $this->assertSame('open', $actions[0]['type']);
        $this->assertSame('buy', $actions[0]['side']);
    }

    public function test_rsi_reversion_buys_when_oversold(): void
    {
        // Steady decline → low RSI.
        $closes = [];
        for ($i = 0; $i < 30; $i++) {
            $closes[] = 1.20000 - $i * 0.0010;
        }
        $this->assertLessThan(30, Indicators::rsi($closes, 14));

        $ctx = new StrategyContext(new ExpertAdvisor(['strategy' => 'rsi_reversion']), ['period' => 14, 'oversold' => 30, 'overbought' => 70], $closes, [], [], []);
        $actions = (new RsiReversionStrategy())->decide($ctx);

        $this->assertSame('buy', $actions[0]['side']);
    }

    // ---- Runner end-to-end ----

    public function test_runner_opens_a_tagged_position_on_signal(): void
    {
        $ins = $this->instrument();
        $user = User::create(['name' => 'E', 'email' => 'e@example.com', 'password' => bcrypt('x')]);
        $account = app(AccountProvisioner::class)->createDemoAccount($user);

        $closes = array_fill(0, 59, 1.10000);
        $closes[] = 1.20000;
        $this->seedCandles($ins, $closes);

        $ea = ExpertAdvisor::create([
            'trading_account_id' => $account->id, 'instrument_id' => $ins->id,
            'name' => 'MA EURUSD', 'strategy' => 'ma_cross', 'timeframe' => 'M5',
            'volume' => 0.10, 'params' => ['fast' => 10, 'slow' => 30], 'magic' => 1234567, 'is_active' => true,
        ]);

        $result = app(ExpertAdvisorRunner::class)->run();

        $this->assertSame(1, $result['opened']);
        $this->assertDatabaseHas('positions', [
            'expert_advisor_id' => $ea->id, 'magic' => 1234567, 'side' => 'buy', 'status' => 'open',
        ]);
    }

    public function test_inactive_ea_does_not_trade(): void
    {
        $ins = $this->instrument();
        $user = User::create(['name' => 'E', 'email' => 'e2@example.com', 'password' => bcrypt('x')]);
        $account = app(AccountProvisioner::class)->createDemoAccount($user);
        $closes = array_fill(0, 59, 1.10000);
        $closes[] = 1.20000;
        $this->seedCandles($ins, $closes);

        ExpertAdvisor::create([
            'trading_account_id' => $account->id, 'instrument_id' => $ins->id,
            'name' => 'MA', 'strategy' => 'ma_cross', 'timeframe' => 'M5',
            'volume' => 0.10, 'params' => ['fast' => 10, 'slow' => 30], 'magic' => 222, 'is_active' => false,
        ]);

        $this->assertSame(0, app(ExpertAdvisorRunner::class)->run()['opened']);
        $this->assertSame(0, Position::count());
    }

    public function test_runner_applies_pip_stop_loss_and_take_profit(): void
    {
        $ins = $this->instrument(1.20000);
        $user = User::create(['name' => 'E', 'email' => 'e6@example.com', 'password' => bcrypt('x')]);
        $account = app(AccountProvisioner::class)->createDemoAccount($user);

        $closes = array_fill(0, 59, 1.10000);
        $closes[] = 1.20000;
        $this->seedCandles($ins, $closes);

        $ea = ExpertAdvisor::create([
            'trading_account_id' => $account->id, 'instrument_id' => $ins->id,
            'name' => 'MA', 'strategy' => 'ma_cross', 'timeframe' => 'M5',
            'volume' => 0.10, 'params' => ['fast' => 10, 'slow' => 30],
            'stop_loss_pips' => 100, 'take_profit_pips' => 200, 'max_positions' => 1,
            'magic' => 999, 'is_active' => true,
        ]);

        app(ExpertAdvisorRunner::class)->run();

        $pos = Position::where('expert_advisor_id', $ea->id)->first();
        $this->assertNotNull($pos);
        // Buy filled at ask 1.20010: SL = ask - 100 pips, TP = ask + 200 pips.
        $this->assertEqualsWithDelta(1.20010 - 0.0100, $pos->stop_loss, 0.00001);
        $this->assertEqualsWithDelta(1.20010 + 0.0200, $pos->take_profit, 0.00001);
    }

    public function test_macd_cross_signals_buy(): void
    {
        // Flat baseline (macd ≈ signal ≈ 0) then a sharp jump on the last bar
        // makes the MACD line cross above its signal line.
        $closes = array_fill(0, 60, 1.10000);
        $closes[] = 1.30000;
        $ctx = new StrategyContext(new ExpertAdvisor(['strategy' => 'macd_cross']), ['fast' => 12, 'slow' => 26, 'signal' => 9], $closes, [], [], []);

        $actions = (new \App\Services\Experts\Strategies\MacdCrossStrategy())->decide($ctx);

        $this->assertNotEmpty($actions);
        $this->assertSame('buy', end($actions)['side']);
    }

    // ---- HTTP API ----

    public function test_strategies_catalog_endpoint(): void
    {
        $ins = $this->instrument();
        $user = User::create(['name' => 'E', 'email' => 'e3@example.com', 'password' => bcrypt('x')]);
        app(AccountProvisioner::class)->createDemoAccount($user);

        $this->actingAs($user)->getJson('/api/experts/strategies')
            ->assertOk()
            ->assertJsonStructure(['data' => [['key', 'label', 'params' => [['key', 'label', 'default']]]]]);
    }

    public function test_attach_toggle_and_remove_ea(): void
    {
        $ins = $this->instrument();
        $user = User::create(['name' => 'E', 'email' => 'e4@example.com', 'password' => bcrypt('x')]);
        app(AccountProvisioner::class)->createDemoAccount($user);

        $create = $this->actingAs($user)->postJson('/api/experts', [
            'strategy' => 'rsi_reversion', 'symbol' => 'EURUSD', 'timeframe' => 'M15',
            'volume' => 0.20, 'params' => ['period' => 9],
        ]);
        $create->assertOk()->assertJsonPath('data.strategy', 'rsi_reversion')->assertJsonPath('data.is_active', true);
        $id = $create->json('data.id');
        $this->assertSame(9, $create->json('data.params.period'));

        $this->actingAs($user)->postJson("/api/experts/{$id}/toggle")
            ->assertOk()->assertJsonPath('data.is_active', false);

        $this->actingAs($user)->deleteJson("/api/experts/{$id}")->assertOk();
        $this->assertDatabaseMissing('expert_advisors', ['id' => $id]);
    }

    public function test_attach_rejects_unknown_strategy(): void
    {
        $ins = $this->instrument();
        $user = User::create(['name' => 'E', 'email' => 'e5@example.com', 'password' => bcrypt('x')]);
        app(AccountProvisioner::class)->createDemoAccount($user);

        $this->actingAs($user)->postJson('/api/experts', [
            'strategy' => 'nope', 'symbol' => 'EURUSD', 'timeframe' => 'M5', 'volume' => 0.1,
        ])->assertStatus(422);
    }
}
