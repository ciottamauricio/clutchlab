<?php

namespace App\Events\Trainings;

use App\Models\TrainingSession;
use Illuminate\Foundation\Events\Dispatchable;

// In-process domain event: "a training was scheduled". The action fires it and moves
// on; what the fact triggers (today: a Discord ping via the EventBus) is listeners'
// business. This is the deliberate one-domain trial of internal domain events — see
// docs/domains/trainings.md before spreading the pattern.
class TrainingScheduled
{
    use Dispatchable;

    public function __construct(public TrainingSession $session) {}
}
