<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Broadcast of the latest market quotes, pushed to the public "quotes" channel
 * after each price refresh. Broadcasts immediately (no queue worker required),
 * so it works straight from the `quotes:poll` loop.
 */
class QuotesUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    /**
     * @param  array<int, array<string, mixed>>  $quotes
     */
    public function __construct(public array $quotes)
    {
    }

    public function broadcastOn(): Channel
    {
        return new Channel('quotes');
    }

    public function broadcastAs(): string
    {
        return 'quotes.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['quotes' => $this->quotes];
    }
}
