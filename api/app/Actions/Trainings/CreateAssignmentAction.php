<?php

namespace App\Actions\Trainings;

use App\Models\TrainingAssignment;
use App\Models\TrainingSession;

class CreateAssignmentAction
{
    /** Idempotent: assigning the same homework twice is a no-op, never an error. */
    public function execute(TrainingSession $session, int $userId, string $map, string $nadeType): TrainingAssignment
    {
        return $session->assignments()->firstOrCreate([
            'user_id' => $userId,
            'map' => $map,
            'nade_type' => $nadeType,
        ]);
    }
}
