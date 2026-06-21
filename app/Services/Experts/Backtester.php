<?php

namespace App\Services\Experts;

use App\Models\ExpertAdvisor;
use App\Models\Instrument;
use Illuminate\Support\Collection;

/**
 * Replays a strategy bar-by-bar over historical candles and reports performance.
 * No live orders are placed; trades are simulated (fills at the bar close, with
 * intrabar SL/TP checks against each bar's high/low).
 *
 * Profit is expressed in the instrument's quote currency.
 */
class Backtester
{
    private const LOOKBACK = 30;

    /**
     * @param  array<string, int|float>  $params
     * @param  Collection<int, \App\Models\Candle>  $candles  oldest → newest
     * @return array<string, mixed>
     */
    public function run(
        Strategy $strategy,
        Instrument $instrument,
        array $params,
        float $volume,
        ?int $slPips,
        ?int $tpPips,
        int $maxPositions,
        Collection $candles,
    ): array {
        $closes = $candles->pluck('close')->map(fn ($v) => (float) $v)->all();
        $highs = $candles->pluck('high')->map(fn ($v) => (float) $v)->all();
        $lows = $candles->pluck('low')->map(fn ($v) => (float) $v)->all();
        $n = count($closes);

        $pip = $instrument->pip_size;
        $digits = $instrument->digits;
        $contract = $instrument->contract_size;
        $ea = new ExpertAdvisor(['strategy' => $strategy->key()]);

        $positions = []; // ['side','entry','sl','tp']
        $trades = [];    // ['side','entry','exit','profit']

        $closeTrade = function (array $p, float $exit) use (&$trades, $volume, $contract) {
            $dir = $p['side'] === 'buy' ? 1 : -1;
            $trades[] = [
                'side'   => $p['side'],
                'entry'  => $p['entry'],
                'exit'   => $exit,
                'profit' => round(($exit - $p['entry']) * $dir * $volume * $contract, 2),
            ];
        };

        for ($i = self::LOOKBACK; $i < $n; $i++) {
            $high = $highs[$i];
            $low = $lows[$i];
            $close = $closes[$i];

            // Intrabar SL/TP.
            foreach ($positions as $k => $p) {
                $exit = null;
                if ($p['side'] === 'buy') {
                    if ($p['sl'] !== null && $low <= $p['sl']) {
                        $exit = $p['sl'];
                    } elseif ($p['tp'] !== null && $high >= $p['tp']) {
                        $exit = $p['tp'];
                    }
                } else {
                    if ($p['sl'] !== null && $high >= $p['sl']) {
                        $exit = $p['sl'];
                    } elseif ($p['tp'] !== null && $low <= $p['tp']) {
                        $exit = $p['tp'];
                    }
                }
                if ($exit !== null) {
                    $closeTrade($p, $exit);
                    unset($positions[$k]);
                }
            }

            $ctx = new StrategyContext(
                $ea,
                $params,
                array_slice($closes, 0, $i + 1),
                array_slice($highs, 0, $i + 1),
                array_slice($lows, 0, $i + 1),
                array_values(array_map(fn ($k, $p) => ['id' => $k, 'side' => $p['side']], array_keys($positions), $positions)),
            );

            foreach ($strategy->decide($ctx) as $action) {
                $type = $action['type'] ?? null;
                if ($type === 'close_all') {
                    foreach ($positions as $p) {
                        $closeTrade($p, $close);
                    }
                    $positions = [];
                } elseif ($type === 'open' && count($positions) < max(1, $maxPositions)) {
                    $isBuy = $action['side'] === 'buy';
                    $positions[] = [
                        'side'  => $action['side'],
                        'entry' => $close,
                        'sl'    => $slPips ? round($isBuy ? $close - $slPips * $pip : $close + $slPips * $pip, $digits) : null,
                        'tp'    => $tpPips ? round($isBuy ? $close + $tpPips * $pip : $close - $tpPips * $pip, $digits) : null,
                    ];
                }
            }
        }

        // Close anything still open at the final price.
        $last = $closes[$n - 1] ?? 0.0;
        foreach ($positions as $p) {
            $closeTrade($p, $last);
        }

        return $this->report($trades, $instrument);
    }

    /**
     * @param  array<int, array{side:string,entry:float,exit:float,profit:float}>  $trades
     * @return array<string, mixed>
     */
    private function report(array $trades, Instrument $instrument): array
    {
        $count = count($trades);
        $wins = 0;
        $grossProfit = 0.0;
        $grossLoss = 0.0;
        $equity = 0.0;
        $peak = 0.0;
        $maxDd = 0.0;
        $curve = [0.0]; // cumulative P/L after each trade, starting at 0

        foreach ($trades as $t) {
            if ($t['profit'] > 0) {
                $wins++;
                $grossProfit += $t['profit'];
            } else {
                $grossLoss += abs($t['profit']);
            }
            $equity += $t['profit'];
            $curve[] = round($equity, 2);
            $peak = max($peak, $equity);
            $maxDd = max($maxDd, $peak - $equity);
        }

        return [
            'symbol'        => $instrument->symbol,
            'currency'      => $instrument->quote_currency,
            'trades'        => $count,
            'wins'          => $wins,
            'losses'        => $count - $wins,
            'win_rate'      => $count > 0 ? round($wins / $count * 100, 1) : null,
            'net_profit'    => round($grossProfit - $grossLoss, 2),
            'gross_profit'  => round($grossProfit, 2),
            'gross_loss'    => round($grossLoss, 2),
            'profit_factor' => $grossLoss > 0 ? round($grossProfit / $grossLoss, 2) : null,
            'max_drawdown'  => round($maxDd, 2),
            'equity'        => $curve,
        ];
    }
}
