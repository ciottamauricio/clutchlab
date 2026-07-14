<?php

namespace App\Actions\Trainings;

use App\Models\TrainingAssignment;

class DeleteAssignmentAction
{
    public function execute(TrainingAssignment $assignment): void
    {
        $assignment->delete();
    }
}
