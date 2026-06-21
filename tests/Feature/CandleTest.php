<?php

namespace Tests\Feature;

use App\Models\Candle;
use App\Models\Instrument;
use App\Models\Quote;
use App\Models\User;
use App\Services\MarketData\CandleService;
use App\Services\MarketData\QuoteService;
use App\Services\Trading\AccountProvisioner;
use Database\Seeders\InstrumentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CandleTest extends TestCase
{
    use RefreshDatabase;

    public function test_bucket_start_aligns_timeframes(): void
    {
        $svc = app(CandleService::class);
        $t = Carbon::parse('2026-06-20 13:37:45', 'UTC');

        $this->assertSame('2026-06-20 13:35:00', $svc->bucketStart($t, 'M5')->toDateTimeString());
        $this->assertSame('2026-06-20 13:00:00', $svc->bucketStart($t, 'H1')->toDateTimeString());
        $this->assertSame('2026-06-20 12:00:00', $svc->bucketStart($t, 'H4')->toDateTimeString());
        $this->assertSame('2026-06-20 00:00:00', $svc->bucketStart($t, 'D1')->toDateTimeString());
        $this->assertSame('2026-06-01 00:00:00', $svc->bucketStart($t, 'MN')->toDateTimeString());
    }

    public function test_ingest_builds_and_updates_a_candle(): void
    {
        $instrument = Instrument::create([
            'symbol' => 'EURUSD', 'base_currency' => 'EUR', 'quote_currency' => 'USD',
            'description' => 'x', 'digits' => 5, 'pip_size' => 0.0001, 'contract_size' => 100000,
            'min_volume' => 0.01, 'max_volume' => 100, 'volume_step' => 0.01, 'is_active' => true,
        ]);
        $svc = app(CandleService::class);
        $t = Carbon::parse('2026-06-20 13:36:10', 'UTC');

        $svc->ingest($instrument, 1.10000, $t);
        $svc->ingest($instrument, 1.10050, $t->copy()->addSeconds(20)); // same M5 bucket
        $svc->ingest($instrument, 1.09900, $t->copy()->addSeconds(40));

        $bar = Candle::where('instrument_id', $instrument->id)->where('timeframe', 'M5')->first();
        $this->assertEqualsWithDelta(1.10000, $bar->open, 0.00001);
        $this->assertEqualsWithDelta(1.10050, $bar->high, 0.00001);
        $this->assertEqualsWithDelta(1.09900, $bar->low, 0.00001);
        $this->assertEqualsWithDelta(1.09900, $bar->close, 0.00001);
        $this->assertSame(3, $bar->volume);
    }

    public function test_seed_history_anchors_last_close_to_current_mid(): void
    {
        $instrument = Instrument::create([
            'symbol' => 'GBPUSD', 'base_currency' => 'GBP', 'quote_currency' => 'USD',
            'description' => 'x', 'digits' => 5, 'pip_size' => 0.0001, 'contract_size' => 100000,
            'min_volume' => 0.01, 'max_volume' => 100, 'volume_step' => 0.01, 'is_active' => true,
        ]);
        $svc = app(CandleService::class);

        $svc->seedHistory($instrument, 'M5', 50, 1.27000);

        $bars = $svc->recent($instrument, 'M5', 50);
        $this->assertCount(50, $bars);
        $this->assertEqualsWithDelta(1.27000, $bars->last()->close, 0.00001);
        // OHLC integrity: high >= max(open, close), low <= min(open, close).
        foreach ($bars as $b) {
            $this->assertGreaterThanOrEqual(max($b->open, $b->close) - 1e-9, $b->high);
            $this->assertLessThanOrEqual(min($b->open, $b->close) + 1e-9, $b->low);
        }
    }

    public function test_candles_endpoint_returns_ohlc(): void
    {
        $this->seed(InstrumentSeeder::class);
        app(QuoteService::class)->refresh();
        $instrument = Instrument::where('symbol', 'EURUSD')->first();
        app(CandleService::class)->seedHistory($instrument, 'M15', 100, 1.085);

        $user = User::create(['name' => 'C', 'email' => 'c@example.com', 'password' => bcrypt('x')]);
        app(AccountProvisioner::class)->createDemoAccount($user);

        $response = $this->actingAs($user)->getJson('/api/instruments/EURUSD/candles?timeframe=M15&limit=100');

        $response->assertOk()
            ->assertJsonPath('data.timeframe', 'M15')
            ->assertJsonStructure(['data' => ['symbol', 'timeframe', 'digits', 'candles' => [['timestamp', 'open', 'high', 'low', 'close', 'volume']]]]);
        $this->assertNotEmpty($response->json('data.candles'));
    }

    public function test_refresh_ingests_live_candles(): void
    {
        $this->seed(InstrumentSeeder::class);

        app(QuoteService::class)->refresh();

        // Each active instrument should now have a live candle for every timeframe.
        $tfCount = count(CandleService::TIMEFRAMES);
        $instrumentCount = Instrument::where('is_active', true)->count();
        $this->assertSame($tfCount * $instrumentCount, Candle::count());
    }
}
