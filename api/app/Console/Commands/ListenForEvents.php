<?php

namespace App\Console\Commands;

use App\Contracts\EventSubscriber;
use App\Events\Subscribers\EventHandler;
use Illuminate\Console\Command;

// Laravel as a subscriber on clutch_events — the mirror of the Go notifier, and the
// general path for turning any cross-service fact into a Laravel-side reaction (today:
// emailing a training's roster). Runs as its own long-lived container (see compose).
// It is NOT the request path, so reactions run inline here — nothing to unblock.
class ListenForEvents extends Command
{
    protected $signature = 'events:listen';

    protected $description = 'Subscribe to the cross-service event channel and dispatch handlers';

    public function handle(EventSubscriber $subscriber): int
    {
        // event name -> the handlers that react to it (many-to-one allowed). Handlers are
        // tagged EventHandler in AppServiceProvider; resolving the tag builds them all.
        $byEvent = [];
        foreach ($this->laravel->tagged(EventHandler::class) as $handler) {
            $byEvent[$handler->handles()][] = $handler;
        }

        $this->info('events:listen — subscribed to '.config('clutch.events_channel'));
        $this->line('handlers: '.(empty($byEvent) ? '(none)' : implode(', ', array_keys($byEvent))));

        $subscriber->listen(function (string $event, array $payload) use ($byEvent) {
            foreach ($byEvent[$event] ?? [] as $handler) {
                $handler->handle($payload);
            }
        });

        return self::SUCCESS;
    }
}
