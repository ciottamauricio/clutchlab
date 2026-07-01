<?php

namespace App\Queue;

use App\Contracts\ParseQueue;
use Illuminate\Contracts\Redis\Factory as Redis;

class RedisParseQueue implements ParseQueue
{
    public function __construct(private Redis $redis) {}

    public function push(int $matchId, string $demoKey): void
    {
        // Plain list + JSON, NOT Laravel's queue format — the Go worker can't read
        // serialized PHP jobs. This is the deliberate polyglot tax (docs/ARCHITECTURE.md).
        $this->redis->connection()->rpush(config('clutch.parse_queue'), json_encode([
            'match_id' => $matchId,
            'demo_key' => $demoKey,
        ]));
    }
}
