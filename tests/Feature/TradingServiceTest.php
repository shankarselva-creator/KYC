<?php

namespace Tests\Feature;

use App\Models\Instrument;
use App\Models\Quote;
use App\Models\TradingAccount;
use App\Models\User;
use App\Services\Trading\TradingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TradingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function instrument(array $overrides = []): Instrument
    {
        return Instrument::create(array_merge([
            'symbol'         => 'EURUSD',
            'base_currency'  => 'EUR',
            'quote_currency' => 'USD',
            'description'    => 'Euro vs US Dollar',
            'digits'         => 5,
            'pip_size'       => 0.0001,
            'contract_size'  => 100000,
            'min_volume'     => 0.01,
            'max_volume'     => 100.00,
            'volume_step'    => 0.01,
        ], $overrides));
    }

    private function account(): TradingAccount
    {
        $user = User::create(['name' => 'T', 'email' => 't@example.com', 'password' => 'x']);

        return TradingAccount::create([
            'user_id'  => $user->id,
            'login'    => '5000001',
            'type'     => 'demo',
            'currency' => 'USD',
            'leverage' => 100,
            'balance'  => 10000,
            'is_active' => true,
        ]);
    }

    public function test_buy_position_profit_and_margin(): void
    {
        $ins = $this->instrument();
        Quote::create(['instrument_id' => $ins->id, 'bid' => 1.10000, 'ask' => 1.10010]);
        $account = $this->account();

        /** @var TradingService $svc */
        $svc = app(TradingService::class);
        $position = $svc->openPosition($account, $ins, 'buy', 1.0);

        $this->assertSame(1.1001, $position->open_price);

        // Margin = volume * contract * mid / leverage = 100000 * ~1.10005 / 100 ≈ 1100.05
        $metrics = $svc->accountMetrics($account->fresh());
        $this->assertEqualsWithDelta(1100.05, $metrics['used_margin'], 0.5);

        // Move price up 50 pips and close — buy closes at bid.
        $ins->quote()->update(['bid' => 1.10500, 'ask' => 1.10510]);
        $closed = $svc->closePosition($position->fresh()->load('instrument', 'tradingAccount'));

        // Profit = (1.10500 - 1.10010) * 100000 = 490 USD
        $this->assertEqualsWithDelta(490.0, $closed->profit, 0.01);
        $this->assertSame('closed', $closed->status);
        $this->assertEqualsWithDelta(10490.0, $account->fresh()->balance, 0.01);
    }

    public function test_sell_position_profit(): void
    {
        $ins = $this->instrument();
        Quote::create(['instrument_id' => $ins->id, 'bid' => 1.20000, 'ask' => 1.20010]);
        $account = $this->account();

        $svc = app(TradingService::class);
        $position = $svc->openPosition($account, $ins, 'sell', 2.0); // sell opens at bid 1.20000

        // Price drops — sell closes at ask.
        $ins->quote()->update(['bid' => 1.19000, 'ask' => 1.19010]);
        $closed = $svc->closePosition($position->fresh()->load('instrument', 'tradingAccount'));

        // Profit = (1.20000 - 1.19010) * 2 lots * 100000 = 1980 USD
        $this->assertEqualsWithDelta(1980.0, $closed->profit, 0.01);
    }

    public function test_rejects_order_without_margin(): void
    {
        $ins = $this->instrument();
        Quote::create(['instrument_id' => $ins->id, 'bid' => 1.10000, 'ask' => 1.10010]);
        $account = $this->account();
        $account->update(['balance' => 50]); // too small for 1 lot

        $svc = app(TradingService::class);

        $this->expectExceptionMessage('Not enough free margin');
        $svc->openPosition($account, $ins, 'buy', 1.0);
    }
}
