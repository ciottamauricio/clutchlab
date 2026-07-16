<?php

namespace App\Events\Subscribers;

use App\Mail\TrainingScheduledMail;
use App\Models\TrainingSession;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

// Reacts to training.scheduled by emailing every rostered player. The event payload
// carries only a player *count* (the wire contract is a fact, not a data dump), so the
// roster is re-read here by training_id — the subscriber owns its own query. Mail sends
// through Laravel's configured transport (log by default; swap to SMTP via env only).
class EmailTrainingRoster implements EventHandler
{
    public function handles(): string
    {
        return 'training.scheduled';
    }

    public function handle(array $payload): void
    {
        $id = $payload['training_id'] ?? null;
        if (! $id) {
            return;
        }

        $session = TrainingSession::with(['team', 'tactics', 'players'])->find($id);
        if (! $session) {
            Log::warning("email roster: training {$id} not found (deleted before delivery?)");

            return;
        }

        foreach ($session->players as $player) {
            if (! $player->email) {
                continue;
            }

            Mail::to($player->email)
                ->locale($player->locale ?? 'en')
                ->send(new TrainingScheduledMail($session, $player->name));
        }
    }
}
