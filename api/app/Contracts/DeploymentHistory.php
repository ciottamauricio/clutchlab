<?php

namespace App\Contracts;

// Source of past deploys, for reconstructing metrics that predate the ingestion endpoint.
// Behind an interface because the CI provider is an implementation detail: GitHub Actions
// today, and a test never calls it at all.
interface DeploymentHistory
{
    /**
     * Completed production deploy runs, newest first, going back no further than $since.
     *
     * Each entry is normalized to the columns a deployment row needs:
     * { service, commit_sha, commit_authored_at, deploy_started_at, deploy_finished_at,
     *   status, actions_run_id }.
     *
     * @return iterable<array<string, mixed>>
     */
    public function since(\DateTimeInterface $since): iterable;
}
