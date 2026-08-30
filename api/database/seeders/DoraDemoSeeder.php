<?php

namespace Database\Seeders;

use App\Models\Deployment;
use App\Models\Incident;
use App\Models\ParseEvent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

// Plausible-but-fake DORA data, so the dashboard can be built and demoed before the
// project has a deploy pipeline. Explicitly NOT wired into DatabaseSeeder: the test suite
// seeds, and metrics tests asserting arithmetic must not find invented rows.
//
//   php artisan db:seed --class=DoraDemoSeeder
//   php artisan db:seed --class=DoraDemoSeeder   # re-running replaces, never doubles
//
// Every synthetic deploy carries a `seed-` prefixed actions_run_id. That marker is what
// keeps fabricated and real data separable — `dora:seed-clear` deletes exactly these rows
// and nothing that CI reported. The rule it enforces: a synthetic incident may only ever
// blame a synthetic deploy. Randomly marking real backfilled deploys as failures would
// make change failure rate a fiction while looking entirely reasonable.
class DoraDemoSeeder extends Seeder
{
    public const MARKER = 'seed-';

    private const DAYS = 30;

    public function run(): void
    {
        $this->clear();

        $deployments = $this->seedDeployments();
        $this->seedIncidents($deployments);
        $this->seedParseEvents();

        $this->command?->info(sprintf(
            'Seeded %d deployments, %d incidents, %d parse events.',
            Deployment::where('actions_run_id', 'like', self::MARKER.'%')->count(),
            Incident::count(),
            ParseEvent::count(),
        ));
    }

    /** Remove a previous run's synthetic rows, leaving anything real untouched. */
    private function clear(): void
    {
        $seeded = Deployment::where('actions_run_id', 'like', self::MARKER.'%');

        Incident::whereIn('deployment_id', (clone $seeded)->pluck('id'))->delete();
        $seeded->delete();
        ParseEvent::whereNull('match_id')->delete();
    }

    /** @return Collection<int, Deployment> */
    private function seedDeployments()
    {
        $services = ['api', 'worker', 'web'];
        $created = collect();

        foreach (range(self::DAYS, 0) as $daysAgo) {
            $day = Carbon::now()->subDays($daysAgo);

            // Weekends are quiet — a flat rate all week is the tell of generated data.
            $deploysToday = $day->isWeekend() ? random_int(0, 1) : random_int(0, 4);

            foreach (range(1, max(0, $deploysToday)) as $n) {
                $service = $services[array_rand($services)];
                $finished = $day->copy()
                    ->setTime(random_int(9, 18), random_int(0, 59), random_int(0, 59));

                // ~7% of deploys fail in the pipeline; those never reached users, so they
                // are not change failures — they are the pipeline doing its job.
                $failedInPipeline = random_int(1, 100) <= 7;

                $created->push(Deployment::create([
                    'service' => $service,
                    'environment' => 'production',
                    'commit_sha' => bin2hex(random_bytes(20)),
                    // Most changes ship the same day; a minority sat on a branch a while.
                    'commit_authored_at' => $finished->copy()->subMinutes(
                        random_int(1, 100) <= 80 ? random_int(20, 600) : random_int(1440, 20160)
                    ),
                    'deploy_started_at' => $finished->copy()->subMinutes(random_int(2, 9)),
                    'deploy_finished_at' => $finished,
                    'status' => $failedInPipeline ? 'failed' : 'success',
                    'caused_failure' => false,
                    'actions_run_id' => self::MARKER.$daysAgo.'-'.$n,
                ]));
            }
        }

        return $created;
    }

    // ~8% of successful deploys break something. The incident is what sets caused_failure,
    // exactly as the live path does — CFR is never written directly.
    private function seedIncidents($deployments): void
    {
        $candidates = $deployments->where('status', 'success');

        foreach ($candidates as $deployment) {
            if (random_int(1, 100) > 8) {
                continue;
            }

            $opened = $deployment->deploy_finished_at->copy()->addMinutes(random_int(5, 90));

            // The most recent incident is left open, so the dashboard exercises the
            // "still burning, excluded from MTTR" path rather than only the tidy case.
            $resolved = $opened->isAfter(Carbon::now()->subDay())
                ? null
                : $opened->copy()->addMinutes(random_int(15, 180));

            Incident::create([
                'service' => $deployment->service,
                'deployment_id' => $deployment->id,
                'opened_at' => $opened,
                'resolved_at' => $resolved,
                'description' => 'Seeded incident for '.$deployment->service,
            ]);

            $deployment->update(['caused_failure' => true]);
        }
    }

    // Parse telemetry can't be backfilled from anywhere: it lives in the worker, and past
    // outcomes were never recorded. Synthetic is the only option here.
    // match_id stays null — these describe no real upload, and that is also what
    // clear() keys on to remove them.
    private function seedParseEvents(): void
    {
        $sloMs = (int) config('clutch.dora.parse_slo_ms');

        foreach (range(self::DAYS, 0) as $daysAgo) {
            foreach (range(1, random_int(20, 120)) as $ignored) {
                $failed = random_int(1, 100) > 97;
                $withinSlo = random_int(1, 100) <= 95;

                $at = Carbon::now()->subDays($daysAgo)->subMinutes(random_int(0, 1439));

                ParseEvent::create([
                    'match_id' => null,
                    'status' => $failed ? 'failed' : 'success',
                    'duration_ms' => $withinSlo
                        ? random_int(30000, $sloMs - 5000)
                        : random_int($sloMs + 1, 400000),
                    'error_code' => $failed ? 'parse_failed_corrupt' : null,
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);
            }
        }
    }
}
