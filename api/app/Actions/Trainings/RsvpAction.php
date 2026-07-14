<?php

namespace App\Actions\Trainings;

use App\Models\TrainingSession;
use App\Models\User;

class RsvpAction
{
    public function execute(TrainingSession $session, User $player, bool $going): void
    {
        $session->players()->updateExistingPivot($player->id, ['rsvp' => $going ? 'in' : 'out']);
    }
}
