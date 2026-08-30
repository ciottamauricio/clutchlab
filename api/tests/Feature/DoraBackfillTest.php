<?php

namespace Tests\Feature;

use App\Contracts\DeploymentHistory;
use App\Models\Deployment;
use App\Models\Incident;
use App\Models\ParseEvent;
use App\Services\Dora\GithubDeploymentHistory;
use Database\Seeders\DoraDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\Http as HttpFacade;
use Tests\TestCase;

// Backfill (real history from CI) and the demo seeder (synthetic), plus the rule that
// keeps them from contaminating each other.
class DoraBackfillTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function workflowRun(array $payload): array
    {
        return array_merge([
            'id' => 111,
            'path' => '.github/workflows/deploy.api.yml',
            'head_sha' => str_repeat('a', 40),
            'head_commit' => ['timestamp' => '2026-08-20T10:00:00Z'],
            'run_started_at' => '2026-08-20T11:00:00Z',
            'updated_at' => '2026-08-20T11:05:00Z',
            'conclusion' => 'success',
        ], $payload);
    }

    private function history(array $runs): DeploymentHistory
    {
        HttpFacade::fake([
            'api.github.com/*' => HttpFacade::response(['workflow_runs' => $runs]),
        ]);

        return new GithubDeploymentHistory(
            app(Http::class),
            'owner/repo',
            '',
            'https://api.github.com',
            config('clutch.dora.deploy_workflows'),
        );
    }

    public function test_a_deploy_run_becomes_a_deployment_row(): void
    {
        $this->app->instance(DeploymentHistory::class, $this->history([$this->workflowRun([])]));

        $this->artisan('dora:backfill-deployments')->assertSuccessful();

        $this->assertDatabaseHas('deployments', [
            'service' => 'api',
            'actions_run_id' => '111',
            'status' => 'success',
        ]);
    }

    // The whole correctness story of the backfill: api.yml and worker.yml in this repo
    // are TEST workflows. Importing them would turn deployment frequency into a count of
    // how often CI ran.
    public function test_test_workflows_are_never_imported_as_deploys(): void
    {
        $this->app->instance(DeploymentHistory::class, $this->history([
            $this->workflowRun(['id' => 1, 'path' => '.github/workflows/api.yml']),
            $this->workflowRun(['id' => 2, 'path' => '.github/workflows/worker.yml']),
            $this->workflowRun(['id' => 3, 'path' => '.github/workflows/frontend.yml']),
            $this->workflowRun(['id' => 4, 'path' => '.github/workflows/study-doc.yml']),
        ]));

        $this->artisan('dora:backfill-deployments')->assertSuccessful();

        $this->assertDatabaseCount('deployments', 0);
    }

    public function test_backfilling_twice_does_not_duplicate(): void
    {
        $this->app->instance(DeploymentHistory::class, $this->history([$this->workflowRun([])]));

        $this->artisan('dora:backfill-deployments')->assertSuccessful();
        $this->artisan('dora:backfill-deployments')->assertSuccessful();

        $this->assertDatabaseCount('deployments', 1);
    }

    public function test_the_same_run_id_across_services_stays_two_deploys(): void
    {
        $this->app->instance(DeploymentHistory::class, $this->history([
            $this->workflowRun(['id' => 5, 'path' => '.github/workflows/deploy.api.yml']),
            $this->workflowRun(['id' => 5, 'path' => '.github/workflows/deploy.worker.yml']),
        ]));

        $this->artisan('dora:backfill-deployments')->assertSuccessful();

        $this->assertDatabaseCount('deployments', 2);
    }

    // Cancelled and timed-out runs are deploys that didn't land; CFR needs to see them.
    public function test_a_cancelled_run_is_recorded_as_a_failed_deploy(): void
    {
        $this->app->instance(DeploymentHistory::class, $this->history([
            $this->workflowRun(['conclusion' => 'cancelled']),
        ]));

        $this->artisan('dora:backfill-deployments')->assertSuccessful();

        $this->assertDatabaseHas('deployments', ['actions_run_id' => '111', 'status' => 'failed']);
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $this->app->instance(DeploymentHistory::class, $this->history([$this->workflowRun([])]));

        $this->artisan('dora:backfill-deployments', ['--dry-run' => true])->assertSuccessful();

        $this->assertDatabaseCount('deployments', 0);
    }

    public function test_runs_older_than_the_window_stop_the_walk(): void
    {
        $this->app->instance(DeploymentHistory::class, $this->history([
            $this->workflowRun(['id' => 9, 'run_started_at' => now()->subDays(400)->toIso8601String()]),
        ]));

        $this->artisan('dora:backfill-deployments', ['--days' => 90])->assertSuccessful();

        $this->assertDatabaseCount('deployments', 0);
    }

    public function test_github_timestamps_are_stored_as_utc(): void
    {
        $this->app->instance(DeploymentHistory::class, $this->history([
            $this->workflowRun([
                'head_commit' => ['timestamp' => '2026-08-20T12:00:00+02:00'],
                'run_started_at' => '2026-08-20T12:30:00+02:00',
                'updated_at' => '2026-08-20T13:00:00+02:00',
            ]),
        ]));

        $this->artisan('dora:backfill-deployments')->assertSuccessful();

        $deployment = Deployment::firstOrFail();

        $this->assertSame('2026-08-20 10:00:00', $deployment->commit_authored_at->utc()->toDateTimeString());
        $this->assertSame(3600, $deployment->leadTimeSeconds());
    }

    public function test_the_seeder_produces_a_dashboard_worth_of_data(): void
    {
        $this->seed(DoraDemoSeeder::class);

        $this->assertGreaterThan(0, Deployment::count());
        $this->assertGreaterThan(0, ParseEvent::count());
        // Every seeded deploy is marked, or seed-clear couldn't tell them apart.
        $this->assertSame(0, Deployment::where('actions_run_id', 'not like', 'seed-%')->count());
    }

    // Counts vary per run (the volumes are randomized), so the property under test is
    // that a reseed REPLACES: the total must stay in the same range, not roughly double.
    public function test_reseeding_replaces_rather_than_doubles(): void
    {
        $this->seed(DoraDemoSeeder::class);
        $first = Deployment::count();

        $this->seed(DoraDemoSeeder::class);
        $second = Deployment::count();

        $this->assertLessThan($first * 2, $second);
        $this->assertSame($second, Deployment::where('actions_run_id', 'like', 'seed-%')->count());
    }

    // The rule that keeps the two data sources honest: a fabricated incident must never
    // blame a deploy that CI actually reported.
    public function test_seeded_incidents_never_blame_a_real_deployment(): void
    {
        $real = Deployment::factory()->create(['actions_run_id' => '99999', 'caused_failure' => false]);

        $this->seed(DoraDemoSeeder::class);

        $this->assertSame(0, Incident::where('deployment_id', $real->id)->count());
        $this->assertFalse($real->fresh()->caused_failure);
    }

    public function test_clearing_removes_seeded_data_and_keeps_real_data(): void
    {
        $real = Deployment::factory()->create(['actions_run_id' => '88888']);
        ParseEvent::factory()->create(['match_id' => 4242]);
        $this->seed(DoraDemoSeeder::class);

        $this->artisan('dora:seed-clear')->assertSuccessful();

        $this->assertDatabaseCount('deployments', 1);
        $this->assertNotNull($real->fresh());
        $this->assertSame(0, Deployment::where('actions_run_id', 'like', 'seed-%')->count());
        // Real telemetry (tied to a match) survives; synthetic (match_id null) does not.
        $this->assertSame(1, ParseEvent::count());
    }
}
