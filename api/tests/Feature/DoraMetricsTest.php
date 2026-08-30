<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Deployment;
use App\Models\Incident;
use App\Models\ParseEvent;
use App\Models\User;
use App\Services\Dora\MetricsCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// The metrics are computed on read from recorded rows, so these tests seed the rows and
// assert the arithmetic — including the cases where the honest answer is "no data".
class DoraMetricsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function calculator(): MetricsCalculator
    {
        return app(MetricsCalculator::class);
    }

    private function window(): array
    {
        return [now()->subDays(30), now()];
    }

    public function test_deployment_frequency_counts_successful_deploys_per_day(): void
    {
        Deployment::factory()->count(30)->create([
            'status' => 'success',
            'deploy_finished_at' => now()->subDays(5),
        ]);

        [$from, $to] = $this->window();
        $result = $this->calculator()->deploymentFrequency($from, $to);

        $this->assertSame(1.0, $result['value']);
        $this->assertSame('elite', $result['bucket']);
    }

    public function test_failed_deploys_are_excluded_from_frequency(): void
    {
        Deployment::factory()->count(10)->failed()->create(['deploy_finished_at' => now()->subDay()]);

        [$from, $to] = $this->window();

        $this->assertNull($this->calculator()->deploymentFrequency($from, $to)['value']);
    }

    public function test_lead_time_is_the_median_not_the_mean(): void
    {
        // One commit that sat for 100 days must not drag the reported figure with it.
        foreach ([3600, 7200, 10800] as $seconds) {
            Deployment::factory()->create([
                'deploy_finished_at' => now()->subDay(),
                'commit_authored_at' => now()->subDay()->subSeconds($seconds),
            ]);
        }
        Deployment::factory()->create([
            'deploy_finished_at' => now()->subDay(),
            'commit_authored_at' => now()->subDay()->subSeconds(8640000),
        ]);

        [$from, $to] = $this->window();
        $result = $this->calculator()->medianLeadTime($from, $to);

        // Median of [3600, 7200, 10800, 8640000] = (7200 + 10800) / 2.
        $this->assertSame(9000, $result['value_seconds']);
        $this->assertSame('elite', $result['bucket']);
    }

    public function test_change_failure_rate_divides_blamed_deploys_by_successful_ones(): void
    {
        Deployment::factory()->count(8)->create(['deploy_finished_at' => now()->subDays(2)]);
        Deployment::factory()->count(2)->create([
            'deploy_finished_at' => now()->subDays(2),
            'caused_failure' => true,
        ]);

        [$from, $to] = $this->window();
        $result = $this->calculator()->changeFailureRate($from, $to);

        $this->assertSame(0.2, $result['value']);
        $this->assertSame('low', $result['bucket']);
    }

    public function test_open_incidents_do_not_count_toward_time_to_restore(): void
    {
        Incident::factory()->create([
            'opened_at' => now()->subDays(3),
            'resolved_at' => now()->subDays(3)->addMinutes(30),
        ]);
        // Still burning: it has no restore time yet, and counting "now" would make MTTR
        // improve every time the page is refreshed.
        Incident::factory()->create(['opened_at' => now()->subDays(3), 'resolved_at' => null]);

        [$from, $to] = $this->window();
        $result = $this->calculator()->medianTimeToRestore($from, $to);

        $this->assertSame(1800, $result['value_seconds']);
        $this->assertSame(1, $result['sample']);
        $this->assertSame('elite', $result['bucket']);
    }

    public function test_reliability_counts_only_successful_parses_inside_the_slo(): void
    {
        ParseEvent::factory()->count(7)->create(['status' => 'success', 'duration_ms' => 60000]);
        ParseEvent::factory()->create(['status' => 'success', 'duration_ms' => 200000]); // too slow
        ParseEvent::factory()->count(2)->create(['status' => 'failed', 'duration_ms' => 5000]);

        [$from, $to] = $this->window();
        $result = $this->calculator()->reliability($from, $to);

        $this->assertSame(0.7, $result['value']);
        $this->assertFalse($result['met']);
    }

    // A parse that finished in exactly the budget met it; the SLO is "within 3 minutes".
    public function test_a_parse_exactly_at_the_budget_still_meets_the_slo(): void
    {
        ParseEvent::factory()->create(['status' => 'success', 'duration_ms' => 180000]);

        [$from, $to] = $this->window();

        $this->assertSame(1.0, $this->calculator()->reliability($from, $to)['value']);
    }

    // The case this project is actually in: nothing deploys yet.
    public function test_metrics_report_no_data_rather_than_a_flattering_zero(): void
    {
        [$from, $to] = $this->window();
        $calc = $this->calculator();

        $this->assertNull($calc->deploymentFrequency($from, $to)['value']);
        $this->assertNull($calc->deploymentFrequency($from, $to)['bucket']);
        $this->assertNull($calc->changeFailureRate($from, $to)['value']);
        $this->assertNull($calc->medianLeadTime($from, $to)['value_seconds']);
        $this->assertNull($calc->medianTimeToRestore($from, $to)['value_seconds']);
        $this->assertNull($calc->reliability($from, $to)['met']);
    }

    public function test_rows_outside_the_window_are_ignored(): void
    {
        Deployment::factory()->count(5)->create(['deploy_finished_at' => now()->subDays(90)]);

        [$from, $to] = $this->window();

        $this->assertNull($this->calculator()->deploymentFrequency($from, $to)['value']);
    }

    public function test_the_endpoint_returns_every_metric_with_a_trend(): void
    {
        Deployment::factory()->count(3)->create(['deploy_finished_at' => now()->subDays(2)]);
        ParseEvent::factory()->count(2)->create();

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Admin]));

        $this->getJson('/api/dora/metrics?window=30')
            ->assertOk()
            ->assertJsonPath('window_days', 30)
            ->assertJsonStructure([
                'window_days', 'generated_at',
                'metrics' => [
                    'deployment_frequency' => ['value', 'unit', 'bucket'],
                    'lead_time' => ['value_seconds', 'bucket'],
                    'change_failure_rate' => ['value', 'bucket'],
                    'time_to_restore' => ['value_seconds', 'bucket'],
                    'reliability' => ['value', 'target', 'met'],
                ],
                'trend' => [['date', 'deploys', 'failures']],
            ]);
    }

    // The dashboard reads metrics/trend off the response ROOT. If this ever grows a
    // Laravel Resource envelope, the frontend's unwrap silently yields undefined and the
    // page renders blank with no error — which is exactly how this broke once.
    public function test_the_payload_is_not_wrapped_in_a_data_envelope(): void
    {
        Deployment::factory()->create(['deploy_finished_at' => now()->subDay()]);

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Admin]));

        $body = $this->getJson('/api/dora/metrics')->assertOk()->json();

        $this->assertArrayNotHasKey('data', $body);
        $this->assertArrayHasKey('metrics', $body);
        $this->assertArrayHasKey('trend', $body);
    }

    public function test_the_dashboard_endpoint_is_admin_only(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/dora/metrics')->assertForbidden();
    }

    public function test_the_dashboard_endpoint_rejects_anonymous_callers(): void
    {
        $this->getJson('/api/dora/metrics')->assertUnauthorized();
    }
}
