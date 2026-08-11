<?php

namespace App\Queue;

use App\Contracts\ParseQueue;
use App\Telemetry\Tracing;
use Illuminate\Contracts\Redis\Factory as Redis;

class RedisParseQueue implements ParseQueue
{
    public function __construct(private Redis $redis, private Tracing $tracing) {}

    public function push(int $matchId, string $demoKey, ?int $ownerId = null, ?string $filename = null): void
    {
        // Plain list + JSON, NOT Laravel's queue format — the Go worker can't read
        // serialized PHP jobs. This is the deliberate polyglot tax (docs/ARCHITECTURE.md).
        // Unescaped slashes so the bytes match Go's serialization (contracts/parse_job.json).
        $job = [
            'match_id' => $matchId,
            'demo_key' => $demoKey,
        ];

        // owner_id + filename let the worker denormalize into analytics.* and echo the
        // filename back on match.parsed without ever reading the matches table (DB split).
        // Additive + optional so the no-extras bytes stay canonical (contracts/parse_job.json).
        if ($ownerId !== null) {
            $job['owner_id'] = $ownerId;
        }
        if ($filename !== null && $filename !== '') {
            $job['filename'] = $filename;
        }

        // Additive, optional: carry the active trace so the worker's parse joins this
        // upload's trace. Omitted when nothing is tracing. Context only — never a data field.
        $traceparent = $this->tracing->traceparent();
        if ($traceparent !== '') {
            $job['traceparent'] = $traceparent;
        }

        $this->redis->connection()->rpush(config('clutch.parse_queue'), json_encode($job, JSON_UNESCAPED_SLASHES));
    }
}
