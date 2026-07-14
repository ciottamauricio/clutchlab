<?php

namespace App\Actions\Trainings;

use App\Models\TrainingAssignment;

class ToggleAssignmentDoneAction
{
    public function execute(TrainingAssignment $assignment, bool $done): TrainingAssignment
    {
        $assignment->done_at = $done ? now() : null;
        $assignment->save();

        return $assignment;
    }
}
