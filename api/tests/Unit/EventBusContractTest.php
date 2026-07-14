<?php

namespace Tests\Unit;

use App\Queue\RedisEventBus;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Tests\TestCase;

// Producer side of the training.scheduled event contract — Laravel's first turn as
// event publisher (contracts/training_scheduled.json; the notifier holds the consumer
// side). Exact bytes: this side defines the canonical serialization.
class EventBusContractTest extends TestCase
{
    public function test_publishes_the_canonical_training_scheduled_payload(): void
    {
        $published = new class
        {
            public array $calls = [];

            public function publish(string $channel, string $payload): void
            {
                $this->calls[] = [$channel, $payload];
            }
        };

        $redis = new class($published) implements RedisFactory
        {
            public function __construct(private object $conn) {}

            public function connection($name = null)
            {
                return $this->conn;
            }
        };

        (new RedisEventBus($redis))->publish('training.scheduled', 1, [
            'training_id' => 7,
            'team' => 'LOLO Clan',
            'title' => 'A-executes + retakes',
            'scheduled_at' => '2026-07-17T21:00:00.000000Z',
            'tactics' => 2,
            'players' => 5,
        ]);

        $fixture = trim(file_get_contents(dirname(base_path()).'/contracts/training_scheduled.json'));

        $this->assertCount(1, $published->calls);
        [$channel, $payload] = $published->calls[0];
        $this->assertSame(config('clutch.events_channel'), $channel);
        $this->assertSame($fixture, $payload);
    }
}
