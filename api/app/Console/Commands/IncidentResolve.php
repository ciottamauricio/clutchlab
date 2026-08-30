<?php

namespace App\Console\Commands;

use App\Actions\Dora\ResolveIncidentAction;
use App\Models\Incident;
use Illuminate\Console\Command;

class IncidentResolve extends Command
{
    protected $signature = 'dora:incident-resolve {id : the incident to close}';

    protected $description = 'Resolve a DORA incident (updates MTTR and CFR on the next read)';

    public function handle(ResolveIncidentAction $action): int
    {
        $incident = Incident::find($this->argument('id'));

        if (! $incident) {
            $this->error("no incident {$this->argument('id')}");

            return self::FAILURE;
        }

        if ($incident->resolved_at) {
            $this->warn("incident {$incident->id} was already resolved at {$incident->resolved_at}");

            return self::SUCCESS;
        }

        $action->execute($incident);
        $this->info("resolved incident {$incident->id} after {$incident->restoreSeconds()}s");

        return self::SUCCESS;
    }
}
