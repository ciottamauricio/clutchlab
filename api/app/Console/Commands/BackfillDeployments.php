<?php

namespace App\Console\Commands;

use App\Actions\Dora\RecordDeploymentAction;
use App\Contracts\DeploymentHistory;
use Illuminate\Console\Command;

// Imports deploy history from CI, for the window that predates the ingestion endpoint.
// Real data, not seeded: every field comes off the workflow run that actually deployed.
//
// Safe to re-run — it writes through the same action the live endpoint uses, so the
// (service, actions_run_id) unique key does the deduplication rather than a second rule
// that could drift from it.
class BackfillDeployments extends Command
{
    protected $signature = 'dora:backfill-deployments
        {--days=90 : how far back to import}
        {--dry-run : report what would be imported without writing}';

    protected $description = 'Import past production deploys from GitHub Actions into the DORA tables';

    public function handle(DeploymentHistory $history, RecordDeploymentAction $record): int
    {
        $since = now()->subDays((int) $this->option('days'));
        $dry = (bool) $this->option('dry-run');

        $seen = 0;
        foreach ($history->since($since) as $deploy) {
            $seen++;
            $this->line(sprintf(
                '  %s  %s  %s  %s',
                str_pad($deploy['service'], 6),
                substr($deploy['commit_sha'], 0, 7),
                $deploy['deploy_finished_at']->toDateTimeString(),
                $deploy['status'],
            ));

            if (! $dry) {
                $record->execute($deploy);
            }
        }

        if ($seen === 0) {
            // The expected result until a deploy workflow has actually run. Say so
            // plainly: a silent "0 imported" reads like a broken command.
            $this->warn('No production deploy runs found in the window.');
            $this->line('Only these workflows count as deploys: '.implode(', ', array_keys(
                config('clutch.dora.deploy_workflows')
            )));
            $this->line('Test workflows are ignored on purpose — see api/docs/domains/dora.md.');

            return self::SUCCESS;
        }

        $this->info($dry
            ? "{$seen} deploys would be imported (dry run — nothing written)."
            : "Imported/updated {$seen} deploys.");

        return self::SUCCESS;
    }
}
