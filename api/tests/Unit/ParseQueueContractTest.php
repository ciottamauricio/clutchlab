<?php

namespace Tests\Unit;

use App\Queue\RedisParseQueue;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Tests\TestCase;

// The producer side of the cross-language queue contract (contracts/parse_job.json).
// This side defines the canonical bytes, so it compares exactly: if the payload shape
// drifts, this fails here and the worker's consumer test fails there — the "same
// commit" rule, enforced.
class ParseQueueContractTest extends TestCase
{
    public function test_pushes_the_canonical_contract_payload(): void
    {
        $pushed = new class
        {
            public array $calls = [];

            public function rpush(string $list, string $payload): void
            {
                $this->calls[] = [$list, $payload];
            }
        };

        $redis = new class($pushed) implements RedisFactory
        {
            public function __construct(private object $conn) {}

            public function connection($name = null)
            {
                return $this->conn;
            }
        };

        (new RedisParseQueue($redis))->push(123, 'demos/abc.dem');

        $fixture = trim(file_get_contents(dirname(base_path()).'/contracts/parse_job.json'));

        $this->assertCount(1, $pushed->calls);
        [$list, $payload] = $pushed->calls[0];
        $this->assertSame(config('clutch.parse_queue'), $list);
        $this->assertSame($fixture, $payload);
    }
}
