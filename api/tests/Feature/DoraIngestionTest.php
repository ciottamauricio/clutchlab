<?php

namespace Tests\Feature;

use App\Events\Subscribers\RecordParseFailed;
use App\Events\Subscribers\RecordParseSucceeded;
use App\Models\Deployment;
use App\Models\Incident;
use App\Models\ParseEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// The ingestion side: how rows get written without a human, and what stops anyone else
// from writing them.
class DoraIngestionTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const TOKEN = 'test-ingest-token';

    protected function setUp(): void
    {
        parent::setUp();
        config(['clutch.dora.token' => self::TOKEN]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'service' => 'api',
            'environment' => 'production',
            'commit_sha' => str_repeat('a', 40),
            'commit_authored_at' => now()->subHours(2)->toIso8601String(),
            'deploy_started_at' => now()->subMinutes(5)->toIso8601String(),
            'deploy_finished_at' => now()->toIso8601String(),
            'status' => 'success',
            'actions_run_id' => '12345',
        ], $overrides);
    }

    public function test_a_deploy_is_recorded_with_no_human_action(): void
    {
        $this->withHeader('X-Internal-Token', self::TOKEN)
            ->postJson('/api/internal/deployments', $this->payload())
            ->assertCreated();

        $this->assertDatabaseHas('deployments', ['service' => 'api', 'actions_run_id' => '12345']);
    }

    public function test_ingestion_without_the_token_is_rejected(): void
    {
        $this->postJson('/api/internal/deployments', $this->payload())->assertUnauthorized();

        $this->assertDatabaseCount('deployments', 0);
    }

    public function test_ingestion_with_a_wrong_token_is_rejected(): void
    {
        $this->withHeader('X-Internal-Token', 'not-the-token')
            ->postJson('/api/internal/deployments', $this->payload())
            ->assertUnauthorized();
    }

    // An unset secret must fail closed. Otherwise forgetting to set DORA_INGEST_TOKEN
    // silently publishes a write endpoint to the internet.
    public function test_an_unconfigured_token_denies_everyone(): void
    {
        config(['clutch.dora.token' => '']);

        $this->withHeader('X-Internal-Token', '')
            ->postJson('/api/internal/deployments', $this->payload())
            ->assertUnauthorized();
    }

    public function test_a_retried_workflow_run_does_not_inflate_the_deploy_count(): void
    {
        foreach (range(1, 3) as $ignored) {
            $this->withHeader('X-Internal-Token', self::TOKEN)
                ->postJson('/api/internal/deployments', $this->payload())
                ->assertCreated();
        }

        $this->assertDatabaseCount('deployments', 1);
    }

    public function test_two_services_in_the_same_workflow_run_are_separate_deploys(): void
    {
        foreach (['api', 'worker'] as $service) {
            $this->withHeader('X-Internal-Token', self::TOKEN)
                ->postJson('/api/internal/deployments', $this->payload(['service' => $service]))
                ->assertCreated();
        }

        $this->assertDatabaseCount('deployments', 2);
    }

    public function test_a_failed_deploy_is_recorded_too(): void
    {
        // The whole point of the always() step in CI: CFR is blind to the failures you
        // most want to see if only successes report.
        $this->withHeader('X-Internal-Token', self::TOKEN)
            ->postJson('/api/internal/deployments', $this->payload(['status' => 'failed']))
            ->assertCreated();

        $this->assertDatabaseHas('deployments', ['status' => 'failed']);
    }

    // A runner in a non-UTC zone sends a real offset. The stored instant must be the same
    // moment, not the same wall clock: lead time is a subtraction between two of these,
    // so a dropped offset silently skews the metric by hours.
    public function test_timestamps_sent_with_an_offset_are_stored_as_utc(): void
    {
        $this->withHeader('X-Internal-Token', self::TOKEN)
            ->postJson('/api/internal/deployments', $this->payload([
                'commit_authored_at' => '2026-08-30T10:00:00+02:00',
                'deploy_started_at' => '2026-08-30T11:00:00+02:00',
                'deploy_finished_at' => '2026-08-30T11:30:00+02:00',
            ]))
            ->assertCreated();

        $deployment = Deployment::firstOrFail();

        $this->assertSame('2026-08-30 08:00:00', $deployment->commit_authored_at->utc()->toDateTimeString());
        $this->assertSame(5400, $deployment->leadTimeSeconds());
    }

    public function test_a_malformed_commit_sha_is_rejected(): void
    {
        $this->withHeader('X-Internal-Token', self::TOKEN)
            ->postJson('/api/internal/deployments', $this->payload(['commit_sha' => 'abc123']))
            ->assertStatus(422);
    }

    public function test_an_unknown_service_is_rejected(): void
    {
        $this->withHeader('X-Internal-Token', self::TOKEN)
            ->postJson('/api/internal/deployments', $this->payload(['service' => 'database']))
            ->assertStatus(422);
    }

    public function test_a_parse_outcome_becomes_telemetry_via_the_existing_event(): void
    {
        app(RecordParseSucceeded::class)->handle([
            'event' => 'match.parsed', 'v' => 1, 'match_id' => 42, 'duration_ms' => 4200,
        ]);

        $this->assertDatabaseHas('parse_events', [
            'match_id' => 42, 'status' => 'success', 'duration_ms' => 4200,
        ]);
    }

    public function test_a_failed_parse_is_recorded_with_its_error_code(): void
    {
        app(RecordParseFailed::class)->handle([
            'event' => 'match.failed', 'v' => 1, 'match_id' => 7,
            'error_code' => 'parse_failed_corrupt', 'duration_ms' => 900,
        ]);

        $this->assertDatabaseHas('parse_events', [
            'match_id' => 7, 'status' => 'failed', 'error_code' => 'parse_failed_corrupt',
        ]);
    }

    // Older workers predate the duration_ms field; the event is still valid and must be
    // recorded, just without a measurement.
    public function test_an_event_without_a_duration_is_recorded_as_unmeasured(): void
    {
        app(RecordParseSucceeded::class)->handle(['event' => 'match.parsed', 'v' => 1, 'match_id' => 9]);

        $this->assertNull(ParseEvent::where('match_id', 9)->first()->duration_ms);
    }

    public function test_blaming_a_deploy_for_an_incident_marks_it_as_a_change_failure(): void
    {
        $deployment = Deployment::factory()->create(['caused_failure' => false]);

        $this->artisan('dora:incident-open', [
            'description' => 'uploads 500ing',
            '--deployment' => $deployment->id,
        ])->assertSuccessful();

        $this->assertTrue($deployment->fresh()->caused_failure);
    }

    public function test_resolving_an_incident_sets_its_restore_time(): void
    {
        $incident = Incident::factory()->create(['opened_at' => now()->subHour(), 'resolved_at' => null]);

        $this->artisan('dora:incident-resolve', ['id' => $incident->id])->assertSuccessful();

        $this->assertNotNull($incident->fresh()->resolved_at);
    }

    public function test_resolving_an_already_resolved_incident_does_not_extend_it(): void
    {
        $resolvedAt = now()->subMinutes(30);
        $incident = Incident::factory()->create([
            'opened_at' => now()->subHour(),
            'resolved_at' => $resolvedAt,
        ]);

        $this->artisan('dora:incident-resolve', ['id' => $incident->id])->assertSuccessful();

        $this->assertSame(
            $resolvedAt->toDateTimeString(),
            $incident->fresh()->resolved_at->toDateTimeString(),
        );
    }
}
