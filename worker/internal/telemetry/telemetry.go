// Package telemetry wires OpenTelemetry tracing: spans exported over OTLP/HTTP to
// whatever backend OTEL_ENDPOINT names (Jaeger here). Tracing is observability, not
// behavior — an unreachable backend must never affect parsing, so exports are
// batched/async and failures only log.
package telemetry

import (
	"context"
	"log"
	"time"

	"go.opentelemetry.io/otel"
	"go.opentelemetry.io/otel/exporters/otlp/otlptrace/otlptracehttp"
	"go.opentelemetry.io/otel/propagation"
	"go.opentelemetry.io/otel/sdk/resource"
	sdktrace "go.opentelemetry.io/otel/sdk/trace"
	semconv "go.opentelemetry.io/otel/semconv/v1.26.0"
)

// Init installs the global tracer provider and W3C traceparent propagator.
// endpoint is host:port without scheme (OTLP/HTTP, insecure — Docker-network only).
// Returns a shutdown func to flush pending spans.
func Init(ctx context.Context, service, endpoint string) (func(context.Context) error, error) {
	exp, err := otlptracehttp.New(ctx,
		otlptracehttp.WithEndpoint(endpoint),
		otlptracehttp.WithInsecure(),
	)
	if err != nil {
		return nil, err
	}

	tp := sdktrace.NewTracerProvider(
		sdktrace.WithBatcher(exp, sdktrace.WithBatchTimeout(2*time.Second)),
		sdktrace.WithResource(resource.NewWithAttributes(
			semconv.SchemaURL,
			semconv.ServiceName(service),
		)),
	)
	otel.SetTracerProvider(tp)
	otel.SetTextMapPropagator(propagation.TraceContext{})
	log.Printf("tracing to %s as %q", endpoint, service)
	return tp.Shutdown, nil
}

// Traceparent serializes the span context of ctx as a W3C traceparent string — the
// cross-service hand-off for non-HTTP hops (it rides the event JSON, not a header).
func Traceparent(ctx context.Context) string {
	carrier := propagation.MapCarrier{}
	otel.GetTextMapPropagator().Inject(ctx, carrier)
	return carrier.Get("traceparent")
}
