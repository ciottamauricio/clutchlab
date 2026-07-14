<?php

namespace App\Listeners\Trainings;

use App\Contracts\EventBus;
use App\Events\Trainings\TrainingScheduled;
use Illuminate\Support\Facades\Log;

// Turns the in-process fact into the cross-service one: training.scheduled on the
// events channel (contracts/training_scheduled.json — producer test pins the bytes).
// Best-effort by the house rule: a notification must never fail the scheduling.
class PublishTrainingScheduled
{
    public function __construct(private EventBus $bus) {}

    public function handle(TrainingScheduled $event): void
    {
        $s = $event->session;

        try {
            $this->bus->publish('training.scheduled', 1, [
                'training_id' => $s->id,
                'team' => $s->team->name,
                'title' => $s->title,
                'scheduled_at' => $s->scheduled_at->toISOString(),
                'tactics' => $s->tactics->count(),
                'players' => $s->players->count(),
            ]);
        } catch (\Throwable $e) {
            Log::warning("training {$s->id}: event publish failed: {$e->getMessage()}");
        }
    }
}
