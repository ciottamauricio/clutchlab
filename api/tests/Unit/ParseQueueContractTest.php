<?php

namespace Tests\Unit;

use App\Queue\RedisParseQueue;
use App\Telemetry\Tracing;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Tests\TestCase;

// The producer side of the cross-language queue contract (contracts/parse_job.json).
// This side defines the canonical bytes, so it compares exactly: if the payload shape
// drifts, this fails here and the worker's consumer test fails there — the "same
// commit" rule, enforced. traceparent is additive and optional (omitted with no active
// trace), so the canonical fixture is the no-trace shape.
class ParseQueueContractTest extends TestCase
{
    private function spyRedis(object $conn): RedisFactory
    {
        return new class($conn) implements RedisFactory
        {
            public function __construct(private object $conn) {}

            public function connection($name = null)
            {
                return $this->conn;
            }
        };
    }

    private function pushSpy(): object
    {
        return new class
        {
            public array $calls = [];

            public function rpush(string $list, string $payload): void
            {
                $this->calls[] = [$list, $payload];
            }
        };
    }

    public function test_pushes_the_canonical_contract_payload(): void
    {
        $pushed = $this->pushSpy();

        // No active trace (test env has an empty OTEL endpoint), so no traceparent is
        // added — the payload is the canonical no-trace shape the fixture pins.
        (new RedisParseQueue($this->spyRedis($pushed), $this->app->make(Tracing::class)))
            ->push(123, 'demos/abc.dem');

        $fixture = trim(file_get_contents(dirname(base_path()).'/contracts/parse_job.json'));

        $this->assertCount(1, $pushed->calls);
        [$list, $payload] = $pushed->calls[0];
        $this->assertSame(config('clutch.parse_queue'), $list);
        $this->assertSame($fixture, $payload);
    }

    public function test_traceparent_is_added_only_when_a_trace_is_active(): void
    {
        $pushed = $this->pushSpy();

        // A tracer with a real (fake) endpoint produces a live span context, so the job
        // gains a traceparent. Its shape is the W3C form; we assert presence, not value.
        $tracing = new Tracing('http://localhost:4318', 'test');
        $span = $tracing->tracer()->spanBuilder('t')->startSpan();
        $scope = $span->activate();

        (new RedisParseQueue($this->spyRedis($pushed), $tracing))->push(7, 'demos/x.dem');

        $scope->detach();
        $span->end();

        $job = json_decode($pushed->calls[0][1], true);
        $this->assertArrayHasKey('traceparent', $job);
        $this->assertMatchesRegularExpression('/^00-[0-9a-f]{32}-[0-9a-f]{16}-[0-9a-f]{2}$/', $job['traceparent']);
    }

    public function test_traceparent_never_smuggles_payload_data(): void
    {
        $pushed = $this->pushSpy();

        $tracing = new Tracing('http://localhost:4318', 'test');
        $span = $tracing->tracer()->spanBuilder('t')->startSpan();
        $scope = $span->activate();

        (new RedisParseQueue($this->spyRedis($pushed), $tracing))->push(7, 'demos/x.dem');

        $scope->detach();
        $span->end();

        // The job may carry only these keys — traceparent is trace context, never a
        // channel for match/demo data (mirrors the worker's ban test on events).
        $job = json_decode($pushed->calls[0][1], true);
        $this->assertEqualsCanonicalizing(['match_id', 'demo_key', 'traceparent'], array_keys($job));
    }
}
