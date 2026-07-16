<?php

namespace App\Queue;

use App\Contracts\EventSubscriber;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

// Blocking SUBSCRIBE on the shared events channel — the consuming twin of RedisEventBus,
// and the same wire the Go notifier reads. At-most-once by nature: messages published
// while this process is down are missed (docs/ARCHITECTURE.md). A malformed or unknown
// payload is logged and skipped so one bad message never kills the listener.
class RedisEventSubscriber implements EventSubscriber
{
    public function listen(callable $handle): void
    {
        $channel = config('clutch.events_channel');

        Redis::subscribe([$channel], function (string $message) use ($handle) {
            $payload = json_decode($message, true);

            if (! is_array($payload) || ! isset($payload['event'])) {
                Log::warning('events:listen: dropped malformed message', ['raw' => $message]);

                return;
            }

            try {
                $handle($payload['event'], $payload);
            } catch (\Throwable $e) {
                // One handler failing (e.g. mail transport hiccup) must not stop the loop.
                Log::error("events:listen: handler for {$payload['event']} failed: {$e->getMessage()}");
            }
        });
    }
}
