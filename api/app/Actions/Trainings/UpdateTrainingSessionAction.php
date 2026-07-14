<?php

namespace App\Actions\Trainings;

use App\Models\TrainingSession;

class UpdateTrainingSessionAction
{
    public function execute(TrainingSession $session, array $data): TrainingSession
    {
        $session->fill(collect($data)->only(['title', 'notes', 'scheduled_at', 'duration_minutes'])->all());

        if (array_key_exists('canceled', $data)) {
            $session->canceled_at = $data['canceled'] ? now() : null;
        }
        $session->save();

        if (array_key_exists('tactic_ids', $data)) {
            $session->tactics()->sync($data['tactic_ids'] ?? []);
        }
        if (array_key_exists('player_ids', $data)) {
            $session->players()->sync($data['player_ids'] ?? []);
        }

        return $session->load(['team', 'creator', 'tactics', 'players']);
    }
}
