<?php

namespace App\Services\Experts;

use App\Models\Instrument;
use Illuminate\Support\Collection;

/**
 * Parameter sweep: backtests a strategy across a grid of parameter
 * combinations and ranks them by net profit, to surface promising settings.
 */
class Optimizer
{
    /** Hard cap on combinations tested, to bound runtime. */
    private const MAX_COMBOS = 200;

    public function __construct(private readonly Backtester $backtester)
    {
    }

    /**
     * @param  array<string, int|float>  $baseParams
     * @param  Collection<int, \App\Models\Candle>  $candles
     * @return array{tested: int, results: array<int, array<string, mixed>>}
     */
    public function optimize(
        Strategy $strategy,
        Instrument $instrument,
        array $baseParams,
        float $volume,
        ?int $slPips,
        ?int $tpPips,
        ?int $trailingPips,
        int $maxPositions,
        Collection $candles,
        int $limit = 15,
    ): array {
        $results = [];
        foreach ($this->combinations($strategy) as $combo) {
            $params = array_merge($baseParams, $combo);
            $r = $this->backtester->run($strategy, $instrument, $params, $volume, $slPips, $tpPips, $trailingPips, $maxPositions, $candles);
            $results[] = [
                'params'        => $combo,
                'net_profit'    => $r['net_profit'],
                'trades'        => $r['trades'],
                'win_rate'      => $r['win_rate'],
                'profit_factor' => $r['profit_factor'],
                'max_drawdown'  => $r['max_drawdown'],
            ];
        }

        usort($results, fn ($a, $b) => $b['net_profit'] <=> $a['net_profit']);

        return ['tested' => count($results), 'results' => array_slice($results, 0, $limit)];
    }

    /**
     * Candidate parameter combinations: each numeric param is swept around its
     * default; the cartesian product is capped at MAX_COMBOS.
     *
     * @return array<int, array<string, int>>
     */
    private function combinations(Strategy $strategy): array
    {
        $candidates = [];
        foreach ($strategy->params() as $p) {
            $d = $p['default'];
            $vals = array_values(array_unique(array_filter(
                array_map('intval', [round($d * 0.5), round($d * 0.75), $d, round($d * 1.5), round($d * 2)]),
                fn ($v) => $v >= 1,
            )));
            $candidates[$p['key']] = $vals;
        }

        $combos = [[]];
        foreach ($candidates as $key => $vals) {
            $next = [];
            foreach ($combos as $c) {
                foreach ($vals as $v) {
                    $next[] = $c + [$key => $v];
                }
            }
            // Stop expanding further params once the grid gets large.
            if (count($next) > self::MAX_COMBOS) {
                break;
            }
            $combos = $next;
        }

        return $combos;
    }
}
