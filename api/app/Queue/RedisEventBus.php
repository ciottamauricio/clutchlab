<?php

namespace App\Queue;

use App\Contracts\EventBus;
use Illuminate\Contracts\Redis\Factory as Redis;

// Redis pub/sub JSON on the shared channel — the same wire the Go worker publishes on
// (worker/internal/events). Unescaped slashes so bytes match Go's serialization.
class RedisEventBus implements EventBus
{
    public function __construct(private Redis $redis) {}

    public function publish(string $event, int $version, array $payload): void
    {
        $this->redis->connection()->publish(
            config('clutch.events_channel'),
            json_encode(['event' => $event, 'v' => $version] + $payload, JSON_UNESCAPED_SLASHES),
        );
    }
}
