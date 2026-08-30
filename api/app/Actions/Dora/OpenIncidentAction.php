<?php

namespace App\Actions\Dora;

use App\Models\Deployment;
use App\Models\Incident;
use Illuminate\Support\Facades\DB;

// Opens an incident and, when it names the deploy that caused it, marks that deploy as a
// change failure. That flip is what makes CFR self-maintaining: blame is recorded once,
// at the moment a human knows it, and the rate recomputes from it forever after.
class OpenIncidentAction
{
    public function execute(array $data): Incident
    {
        return DB::transaction(function () use ($data) {
            $incident = Incident::create([
                'service' => $data['service'] ?? null,
                'deployment_id' => $data['deployment_id'] ?? null,
                'opened_at' => $data['opened_at'] ?? now(),
                'description' => $data['description'],
            ]);

            if ($incident->deployment_id) {
                Deployment::whereKey($incident->deployment_id)->update(['caused_failure' => true]);
            }

            return $incident;
        });
    }
}
