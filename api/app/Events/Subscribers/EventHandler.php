<?php

namespace App\Events\Subscribers;

// One reaction to one incoming cross-service fact. The listener routes a decoded event
// to every handler whose handles() matches its name. Adding a reaction (e.g. email on a
// new event) is a new class registered in the map — never a switch statement to edit.
interface EventHandler
{
    /** The event name this handler reacts to, e.g. "training.scheduled". */
    public function handles(): string;

    /** @param array $payload the full decoded event ({ event, v, ...fields }). */
    public function handle(array $payload): void;
}
