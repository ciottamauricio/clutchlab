<?php

namespace App\Services\Dora;

use App\Contracts\DeploymentHistory;
use Carbon\Carbon;
use Illuminate\Http\Client\Factory as Http;
use RuntimeException;

// Reads past deploys from the GitHub Actions runs API. Run metadata is retained well past
// the 90-day log expiry, so a long backfill window is safe even though the logs are gone.
//
// Only the workflows named in config('clutch.dora.deploy_workflows') are returned. That
// filter is the whole correctness story: this repo's api.yml and worker.yml are TEST
// workflows, and importing them would turn deployment frequency into a count of how often
// CI ran — a number that looks like delivery and measures nothing of the kind.
class GithubDeploymentHistory implements DeploymentHistory
{
    private const PER_PAGE = 100;

    public function __construct(
        private Http $http,
        private string $repo,
        private string $token,
        private string $api,
        /** @var array<string, string> workflow path => service */
        private array $workflows,
    ) {}

    public function since(\DateTimeInterface $since): iterable
    {
        $since = Carbon::instance(Carbon::parse($since));

        for ($page = 1; ; $page++) {
            $runs = $this->page($page);

            foreach ($runs as $run) {
                // Runs come back newest-first, so the first one older than the window
                // ends the walk — no point paging through years of history.
                if (Carbon::parse($run['run_started_at'])->lt($since)) {
                    return;
                }

                $service = $this->workflows[$run['path']] ?? null;
                if ($service === null) {
                    continue;
                }

                yield $this->normalize($run, $service);
            }

            if (count($runs) < self::PER_PAGE) {
                return;
            }
        }
    }

    private function page(int $page): array
    {
        $request = $this->http->acceptJson()
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28']);

        // A public repo reads fine unauthenticated; a token only raises the rate limit
        // (60/hour/IP without one, which a couple of backfills will exhaust).
        if ($this->token !== '') {
            $request = $request->withToken($this->token);
        }

        $response = $request->get("{$this->api}/repos/{$this->repo}/actions/runs", [
            'branch' => 'main',
            'event' => 'push',
            'status' => 'completed',
            'per_page' => self::PER_PAGE,
            'page' => $page,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                "GitHub runs API failed ({$response->status()}): ".$response->body()
            );
        }

        return $response->json('workflow_runs') ?? [];
    }

    private function normalize(array $run, string $service): array
    {
        return [
            'service' => $service,
            'environment' => 'production',
            'commit_sha' => $run['head_sha'],
            // A run triggered by something other than a push may carry no head_commit;
            // falling back to the run's own start keeps lead time defined (as zero-ish)
            // rather than dropping the deploy entirely.
            'commit_authored_at' => Carbon::parse(
                $run['head_commit']['timestamp'] ?? $run['run_started_at']
            )->utc(),
            'deploy_started_at' => Carbon::parse($run['run_started_at'])->utc(),
            'deploy_finished_at' => Carbon::parse($run['updated_at'])->utc(),
            // Anything not a clean success is a failed deploy: cancelled and timed_out
            // are deploys that didn't land, and CFR needs to see them.
            'status' => $run['conclusion'] === 'success' ? 'success' : 'failed',
            'actions_run_id' => (string) $run['id'],
        ];
    }
}
