<?php

namespace App\Actions\Dora;

use App\Models\Deployment;

// Records one deploy. Idempotent on (service, actions_run_id): the CI step that calls
// this runs under `if: always()` and may be retried, and a retried workflow must not
// read as a second deploy — deployment frequency is the metric most easily inflated by
// its own plumbing.
class RecordDeploymentAction
{
    public function execute(array $data): Deployment
    {
        $runId = $data['actions_run_id'] ?? null;

        if ($runId === null) {
            return Deployment::create($data);
        }

        return Deployment::updateOrCreate(
            ['service' => $data['service'], 'actions_run_id' => $runId],
            $data,
        );
    }
}
