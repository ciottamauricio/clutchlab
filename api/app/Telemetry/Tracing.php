<?php

namespace App\Telemetry;

use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\Attributes\ServiceAttributes;

// The api's OpenTelemetry setup — the PHP mirror of worker/internal/telemetry. Spans
// export over OTLP/HTTP to OTEL_ENDPOINT (Jaeger in dev), batched so an unreachable
// backend never blocks a request. Registered as a singleton in AppServiceProvider and
// flushed on terminate. An empty endpoint disables tracing entirely (the tracer becomes
// a no-op) so tests and keyless setups pay nothing.
class Tracing
{
    private ?TracerProvider $provider = null;

    private TracerInterface $tracer;

    public function __construct(string $endpoint, private string $service)
    {
        if ($endpoint === '') {
            // No-op tracer: spans are created but never exported. Behavior unchanged.
            $this->tracer = (new TracerProvider([]))->getTracer($this->service);

            return;
        }

        // The Go services take a scheme-less host:port (OTEL_ENDPOINT=jaeger:4318); PHP's
        // OTLP transport wants a full URL with the signal path. Normalize so the one env
        // var works for both languages.
        if (! str_contains($endpoint, '://')) {
            $endpoint = 'http://'.$endpoint;
        }

        $transport = (new OtlpHttpTransportFactory)->create(
            rtrim($endpoint, '/').'/v1/traces',
            'application/x-protobuf',
        );

        $this->provider = TracerProvider::builder()
            ->addSpanProcessor(BatchSpanProcessor::builder(new SpanExporter($transport))->build())
            ->setResource(ResourceInfo::create(Attributes::create([
                ServiceAttributes::SERVICE_NAME => $this->service,
            ])))
            ->build();

        $this->tracer = $this->provider->getTracer($this->service);
    }

    public function tracer(): TracerInterface
    {
        return $this->tracer;
    }

    // Serialize the active span context as a W3C traceparent — the cross-service hand-off
    // for the non-HTTP hop into the parse queue (it rides the job JSON, not a header).
    public function traceparent(): string
    {
        $carrier = [];
        TraceContextPropagator::getInstance()->inject($carrier, null, Context::getCurrent());

        return $carrier['traceparent'] ?? '';
    }

    // Inverse of traceparent(): given a W3C traceparent from an incoming event, return the
    // Context whose spans join that trace. Empty/malformed → the current context unchanged
    // (a new local root), so a missing hand-off degrades to a local trace, never an error.
    public function extract(string $traceparent): Context
    {
        if ($traceparent === '') {
            return Context::getCurrent();
        }

        return TraceContextPropagator::getInstance()->extract(['traceparent' => $traceparent]);
    }

    // Flush pending spans. Called on terminate so a short-lived request-response still
    // ships its batch before the process ends.
    public function shutdown(): void
    {
        $this->provider?->shutdown();
    }
}
