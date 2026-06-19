<?php

namespace App\Console\Commands;

use App\Services\MarketData\QuoteService;
use Illuminate\Console\Command;

class PollQuotes extends Command
{
    protected $signature = 'quotes:poll {--loop : Continuously poll until interrupted} {--interval=1 : Seconds between polls in loop mode}';

    protected $description = 'Fetch the latest market quotes and store them';

    public function handle(QuoteService $quotes): int
    {
        $loop = (bool) $this->option('loop');
        $interval = max(1, (int) $this->option('interval'));

        do {
            $count = $quotes->refresh();
            $this->info(sprintf('[%s] Updated %d instrument(s) via "%s" driver.',
                now()->toTimeString(), $count, config('markets.driver')));

            if ($loop) {
                sleep($interval);
            }
        } while ($loop);

        return self::SUCCESS;
    }
}
