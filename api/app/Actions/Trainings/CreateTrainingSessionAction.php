<?php

namespace App\Actions\Trainings;

use App\Events\Trainings\TrainingScheduled;
use App\Models\Team;
use App\Models\TrainingSession;
use App\Models\User;

class CreateTrainingSessionAction
{
    public function __construct(private CreateAssignmentAction $assign) {}

    /** @param array{title: string, notes?: ?string, scheduled_at: string, duration_minutes?: ?int, tactic_ids?: ?array, player_ids?: ?array, assignments?: ?array} $data */
    public function execute(Team $team, User $creator, array $data): TrainingSession
    {
        $session = new TrainingSession([
            'title' => $data['title'],
            'notes' => $data['notes'] ?? null,
            'scheduled_at' => $data['scheduled_at'],
            'duration_minutes' => $data['duration_minutes'] ?? null,
        ]);
        $session->team_id = $team->id;
        $session->created_by = $creator->id;
        $session->save();

        $session->tactics()->sync($data['tactic_ids'] ?? []);
        $session->players()->sync($data['player_ids'] ?? []);

        // Homework picked while scheduling — the roster now exists, so assignees resolve.
        foreach ($data['assignments'] ?? [] as $a) {
            $this->assign->execute($session, (int) $a['user_id'], $a['map'], $a['nade_type']);
        }

        $session->load(['team', 'creator', 'tactics', 'players', 'assignments']);

        TrainingScheduled::dispatch($session);

        return $session;
    }
}
