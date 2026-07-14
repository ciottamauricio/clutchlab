<?php

namespace App\Contracts;

// The publish side of the cross-service event channel — facts ("this happened"), not
// commands. Publishers don't know their subscribers; delivery is fire-and-forget by
// design (docs/ARCHITECTURE.md, "Event-driven notifications"). Mirrors the worker's
// events.Publisher: swapping the transport is a new implementation, not a caller change.
interface EventBus
{
    /**
     * Publish one event. $event is the type key (e.g. "training.scheduled"), $version
     * its contract version, $payload the additive-only fields. The wire shape is
     * { "event": ..., "v": ..., ...payload } — see contracts/.
     */
    public function publish(string $event, int $version, array $payload): void;
}
