<?php

namespace App\Actions\Dora;

use App\Models\Incident;

// Closes an incident. MTTR and CFR are computed on read, so resolving one is all it takes
// for both to move — no redeploy, no recomputation step to forget.
class ResolveIncidentAction
{
    public function execute(Incident $incident, ?\DateTimeInterface $at = null): Incident
    {
        // Already-resolved incidents keep their original resolution time: re-running this
        // must not stretch an outage that ended.
        if ($incident->resolved_at === null) {
            $incident->resolved_at = $at ?? now();
            $incident->save();
        }

        return $incident;
    }
}
