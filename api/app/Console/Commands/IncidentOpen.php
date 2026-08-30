<?php

namespace App\Console\Commands;

use App\Actions\Dora\OpenIncidentAction;
use App\Models\Deployment;
use Illuminate\Console\Command;

// Incidents are the one DORA input with no automatic source — "this broke in a way that
// mattered" is a judgement, not a signal. A command keeps the bookkeeping to one line
// rather than a form to build and maintain.
class IncidentOpen extends Command
{
    protected $signature = 'dora:incident-open
        {description : what broke}
        {--service= : api|worker|web; omit when it spans services}
        {--deployment= : id of the deploy to blame (flips its caused_failure)}';

    protected $description = 'Open a DORA incident, optionally blaming a deployment';

    public function handle(OpenIncidentAction $action): int
    {
        $deploymentId = $this->option('deployment');

        if ($deploymentId !== null && ! Deployment::whereKey($deploymentId)->exists()) {
            $this->error("no deployment {$deploymentId}");

            return self::FAILURE;
        }

        $incident = $action->execute([
            'description' => $this->argument('description'),
            'service' => $this->option('service'),
            'deployment_id' => $deploymentId,
        ]);

        $this->info("opened incident {$incident->id}".
            ($deploymentId ? " (deployment {$deploymentId} marked as a change failure)" : ''));

        return self::SUCCESS;
    }
}
