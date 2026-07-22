<?php

namespace App\Contracts;

// The subscribe side of the cross-service event channel — the mirror of EventBus. A
// subscriber tolerates fields (and whole events) it doesn't know: the channel is
// additive one-to-many, so unknown events are skipped, not errors (docs/ARCHITECTURE.md).
// Mirrors the notifier's sub.Subscriber in Go; swapping the transport is a new impl.
interface EventSubscriber
{
    /**
     * Block on the channel forever, calling $handle($event, $payload) for each decoded
     * message. $event is the type key (e.g. "training.scheduled"); $payload is the full
     * decoded JSON (event + v + fields). Malformed messages are skipped, not thrown.
     *
     * @param  callable(string, array): void  $handle
     */
    public function listen(callable $handle): void;
}
