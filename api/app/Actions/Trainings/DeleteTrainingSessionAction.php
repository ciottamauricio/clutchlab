<?php

namespace App\Actions\Trainings;

use App\Models\TrainingSession;

class DeleteTrainingSessionAction
{
    public function execute(TrainingSession $session): void
    {
        // Pivots cascade via FK; nothing external to clean up (unlike matches).
        $session->delete();
    }
}
