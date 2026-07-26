<?php

namespace App\Queue;

use App\Contracts\ParseQueue;
use App\Telemetry\Tracing;
use Illuminate\Contracts\Redis\Factory as Redis;

class RedisParseQueue implements ParseQueue
{
    public function __construct(private Redis $redis, private Tracing $tracing) {}

    public function push(int $matchId, string $demoKey): void
    {
        // Plain list + JSON, NOT Laravel's queue format — the Go worker can't read
        // serialized PHP jobs. This is the deliberate polyglot tax (docs/ARCHITECTURE.md).
        // Unescaped slashes so the bytes match Go's serialization (contracts/parse_job.json).
        $job = [
            'match_id' => $matchId,
            'demo_key' => $demoKey,
        ];

        // Additive, optional: carry the active trace so the worker's parse joins this
        // upload's trace. Omitted when nothing is tracing, so the no-trace bytes stay
        // canonical (contracts/parse_job.json). Context only — never a data field.
        $traceparent = $this->tracing->traceparent();
        if ($traceparent !== '') {
            $job['traceparent'] = $traceparent;
        }

        $this->redis->connection()->rpush(config('clutch.parse_queue'), json_encode($job, JSON_UNESCAPED_SLASHES));
    }
}
