<?php

namespace App\Console\Commands;

use App\Services\MarketData\QuoteService;
use App\Services\Trading\TradingEngine;
use Illuminate\Console\Command;

class PollQuotes extends Command
{
    protected $signature = 'quotes:poll {--loop : Continuously poll until interrupted} {--interval=1 : Seconds between polls in loop mode}';

    protected $description = 'Fetch the latest market quotes, then run the matching engine (pending orders + SL/TP)';

    public function handle(QuoteService $quotes, TradingEngine $engine): int
    {
        $loop = (bool) $this->option('loop');
        $interval = max(1, (int) $this->option('interval'));

        do {
            $count = $quotes->refresh();
            $result = $engine->tick();

            $this->info(sprintf(
                '[%s] Updated %d instrument(s) via "%s" · filled %d, expired %d, closed %d.',
                now()->toTimeString(), $count, config('markets.driver'),
                $result['filled'], $result['expired'], $result['closed'],
            ));

            if ($loop) {
                sleep($interval);
            }
        } while ($loop);

        return self::SUCCESS;
    }
}
