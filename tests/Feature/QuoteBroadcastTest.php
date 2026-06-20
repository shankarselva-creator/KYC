<?php

namespace Tests\Feature;

use App\Events\QuotesUpdated;
use App\Services\MarketData\QuoteService;
use Database\Seeders\InstrumentSeeder;
use Illuminate\Broadcasting\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class QuoteBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_broadcasts_quotes_on_the_public_channel(): void
    {
        $this->seed(InstrumentSeeder::class);
        Event::fake([QuotesUpdated::class]);

        app(QuoteService::class)->refresh();

        Event::assertDispatched(QuotesUpdated::class, function (QuotesUpdated $event) {
            $this->assertNotEmpty($event->quotes);
            $this->assertArrayHasKey('symbol', $event->quotes[0]);
            $this->assertArrayHasKey('bid', $event->quotes[0]);
            $this->assertInstanceOf(Channel::class, $event->broadcastOn());
            $this->assertSame('quotes', $event->broadcastOn()->name);
            $this->assertSame('quotes.updated', $event->broadcastAs());

            return true;
        });
    }
}
