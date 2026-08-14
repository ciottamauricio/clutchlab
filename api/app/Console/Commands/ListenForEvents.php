<?php

namespace App\Console\Commands;

use App\Contracts\EventSubscriber;
use App\Events\Subscribers\EventHandler;
use App\Telemetry\Tracing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use OpenTelemetry\API\Trace\SpanKind;

// Laravel as a subscriber on clutch_events — the mirror of the Go notifier, and the
// general path for turning any cross-service fact into a Laravel-side reaction (today:
// emailing a training's roster). Runs as its own long-lived container (see compose).
// It is NOT the request path, so reactions run inline here — nothing to unblock.
class ListenForEvents extends Command
{
    protected $signature = 'events:listen';

    protected $description = 'Subscribe to the cross-service event channel and dispatch handlers';

    public function handle(EventSubscriber $subscriber, Tracing $tracing): int
    {
        // phpredis reads the blocking SUBSCRIBE through PHP's default_socket_timeout (60s),
        // which config('database.redis.*.read_timeout') does NOT override. Idle longer than
        // that between events and the socket read fails: the process exits 0 and every
        // outcome published afterwards is lost, stranding matches mid-pipeline. This is the
        // one process that must wait forever, so it opts out here rather than in php.ini.
        ini_set('default_socket_timeout', '-1');

        // event name -> the handlers that react to it (many-to-one allowed). Handlers are
        // tagged EventHandler in AppServiceProvider; resolving the tag builds them all.
        $byEvent = [];
        foreach ($this->laravel->tagged(EventHandler::class) as $handler) {
            $byEvent[$handler->handles()][] = $handler;
        }

        $this->info('events:listen — subscribed to '.config('clutch.events_channel'));
        $this->line('handlers: '.(empty($byEvent) ? '(none)' : implode(', ', array_keys($byEvent))));

        $subscriber->listen(function (string $event, array $payload) use ($byEvent, $tracing) {
            $handlers = $byEvent[$event] ?? [];
            if ($handlers === []) {
                return;
            }

            // Join the publisher's trace via the event's traceparent, so this reaction
            // (e.g. apply match.parsed → UPDATE matches) shows as a child span in Jaeger —
            // the api-side step the DB split introduced. No traceparent → a local trace.
            $parent = $tracing->extract($payload['traceparent'] ?? '');
            $span = $tracing->tracer()->spanBuilder('handle '.$event)
                ->setParent($parent)
                ->setSpanKind(SpanKind::KIND_CONSUMER)
                ->startSpan();
            $scope = $span->activate();

            try {
                foreach ($handlers as $handler) {
                    // Isolated per handler: reactions to one fact are independent, and one
                    // failing must not skip the rest. This stopped being theoretical once a
                    // handler started calling an external service (embedding hits ollama) —
                    // a down container would otherwise suppress the handlers after it.
                    try {
                        $handler->handle($payload);
                    } catch (\Throwable $e) {
                        Log::error(sprintf(
                            'events:listen: %s failed on %s: %s',
                            class_basename($handler), $event, $e->getMessage(),
                        ));
                    }
                }
            } finally {
                $scope->detach();
                $span->end();
            }
        });

        return self::SUCCESS;
    }
}
