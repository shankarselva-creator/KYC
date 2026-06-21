<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponses;
use App\Http\Controllers\Concerns\ResolvesTradingAccount;
use App\Http\Controllers\Controller;
use App\Models\ExpertAdvisor;
use App\Models\Instrument;
use App\Services\Experts\Backtester;
use App\Services\Experts\StrategyRegistry;
use App\Services\MarketData\CandleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpertAdvisorController extends Controller
{
    use ApiResponses;
    use ResolvesTradingAccount;

    public function __construct(private readonly StrategyRegistry $registry)
    {
    }

    /** Catalog of available strategies + their parameter schemas. */
    public function strategies(): JsonResponse
    {
        return $this->ok($this->registry->catalog());
    }

    public function index(Request $request): JsonResponse
    {
        $account = $this->activeAccount($request);

        $eas = ExpertAdvisor::where('trading_account_id', $account->id)
            ->with('instrument:id,symbol')
            ->withCount(['positions as open_positions_count' => fn ($q) => $q->where('status', 'open')])
            ->latest()
            ->get()
            ->map(fn (ExpertAdvisor $ea) => $this->present($ea));

        return $this->ok($eas);
    }

    public function store(Request $request): JsonResponse
    {
        $account = $this->activeAccount($request);

        $validated = $request->validate([
            'strategy'  => ['required', 'string'],
            'symbol'    => ['required', 'string', 'exists:instruments,symbol'],
            'timeframe' => ['required', 'in:'.implode(',', array_keys(CandleService::TIMEFRAMES))],
            'volume'    => ['required', 'numeric', 'min:0.01', 'max:100'],
            'params'    => ['nullable', 'array'],
            'stop_loss_pips'   => ['nullable', 'integer', 'min:0', 'max:100000'],
            'take_profit_pips' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'trailing_stop_pips' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'max_positions'    => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        if (! $this->registry->has($validated['strategy'])) {
            return $this->fail('UNKNOWN_STRATEGY', 'That strategy does not exist.', 422);
        }

        $instrument = Instrument::where('symbol', $validated['symbol'])->firstOrFail();
        $strategy = $this->registry->get($validated['strategy']);

        // Keep only known params, coerced to numbers, falling back to defaults.
        $params = $this->registry->defaults($validated['strategy']);
        foreach ($strategy->params() as $p) {
            if (isset($validated['params'][$p['key']]) && is_numeric($validated['params'][$p['key']])) {
                $params[$p['key']] = 0 + $validated['params'][$p['key']];
            }
        }

        $ea = ExpertAdvisor::create([
            'trading_account_id' => $account->id,
            'instrument_id'      => $instrument->id,
            'name'               => $strategy->label().' '.$instrument->symbol,
            'strategy'           => $strategy->key(),
            'timeframe'          => $validated['timeframe'],
            'volume'             => (float) $validated['volume'],
            'params'             => $params,
            'stop_loss_pips'     => $validated['stop_loss_pips'] ?? null,
            'take_profit_pips'   => $validated['take_profit_pips'] ?? null,
            'trailing_stop_pips' => $validated['trailing_stop_pips'] ?? null,
            'max_positions'      => $validated['max_positions'] ?? 1,
            'magic'              => random_int(1_000_000, 9_999_999),
            'is_active'          => true,
        ]);

        return $this->ok($this->present($ea->load('instrument:id,symbol')));
    }

    /** Replay a strategy over recent candles and return a performance report. */
    public function backtest(Request $request, Backtester $backtester, CandleService $candles): JsonResponse
    {
        $this->activeAccount($request);

        $validated = $request->validate([
            'strategy'  => ['required', 'string'],
            'symbol'    => ['required', 'string', 'exists:instruments,symbol'],
            'timeframe' => ['required', 'in:'.implode(',', array_keys(CandleService::TIMEFRAMES))],
            'volume'    => ['required', 'numeric', 'min:0.01', 'max:100'],
            'params'    => ['nullable', 'array'],
            'stop_loss_pips'   => ['nullable', 'integer', 'min:0', 'max:100000'],
            'take_profit_pips' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'max_positions'    => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $strategy = $this->registry->get($validated['strategy']);
        if (! $strategy) {
            return $this->fail('UNKNOWN_STRATEGY', 'That strategy does not exist.', 422);
        }

        $instrument = Instrument::where('symbol', $validated['symbol'])->firstOrFail();
        $bars = $candles->recent($instrument, $validated['timeframe'], 500);
        if ($bars->count() < 50) {
            return $this->fail('NO_HISTORY', 'Not enough candle history to backtest this symbol/timeframe.', 422);
        }

        $params = $this->registry->defaults($validated['strategy']);
        foreach ($strategy->params() as $p) {
            if (isset($validated['params'][$p['key']]) && is_numeric($validated['params'][$p['key']])) {
                $params[$p['key']] = 0 + $validated['params'][$p['key']];
            }
        }

        $report = $backtester->run(
            $strategy, $instrument, $params, (float) $validated['volume'],
            $validated['stop_loss_pips'] ?? null, $validated['take_profit_pips'] ?? null,
            $validated['max_positions'] ?? 1, $bars,
        );
        $report['bars'] = $bars->count();
        $report['strategy'] = $strategy->key();
        $report['timeframe'] = $validated['timeframe'];

        return $this->ok($report);
    }

    public function toggle(Request $request, ExpertAdvisor $expert): JsonResponse
    {
        $account = $this->activeAccount($request);
        if ($expert->trading_account_id !== $account->id) {
            return $this->fail('FORBIDDEN', 'This EA does not belong to your account.', 403);
        }

        $expert->update(['is_active' => ! $expert->is_active]);

        return $this->ok($this->present($expert->load('instrument:id,symbol')));
    }

    public function destroy(Request $request, ExpertAdvisor $expert): JsonResponse
    {
        $account = $this->activeAccount($request);
        if ($expert->trading_account_id !== $account->id) {
            return $this->fail('FORBIDDEN', 'This EA does not belong to your account.', 403);
        }

        $expert->delete();

        return $this->ok(['deleted' => true]);
    }

    private function present(ExpertAdvisor $ea): array
    {
        return [
            'id'         => $ea->id,
            'name'       => $ea->name,
            'strategy'   => $ea->strategy,
            'symbol'     => $ea->instrument->symbol,
            'timeframe'  => $ea->timeframe,
            'volume'     => $ea->volume,
            'params'     => $ea->params,
            'stop_loss_pips'   => $ea->stop_loss_pips,
            'take_profit_pips' => $ea->take_profit_pips,
            'trailing_stop_pips' => $ea->trailing_stop_pips,
            'max_positions'    => $ea->max_positions,
            'magic'      => $ea->magic,
            'is_active'  => $ea->is_active,
            'open_count' => $ea->open_positions_count ?? $ea->positions()->where('status', 'open')->count(),
            'last_run_at' => $ea->last_run_at?->toIso8601String(),
        ];
    }
}
