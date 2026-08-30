# DORA delivery metrics domain

## Status

**Partially measurable by design.** The instrumentation is complete; two of the five
metrics have a real data source today, and three are waiting on a production environment
that does not exist yet (see [Honest gaps](#honest-gaps)).

## Purpose

Measure how well the project *delivers*, from data the system records about itself. The
four DORA metrics plus a Reliability SLO, computed on read from three tables. No metric
requires manual bookkeeping except opening and closing incidents, which has no automatic
signal and shouldn't get a fake one.

| Metric | Question | Source |
|---|---|---|
| Deployment frequency | how often do we ship? | `deployments` |
| Lead time for changes | commit authored → running in production | `deployments` |
| Change failure rate | what share of deploys broke something? | `deployments.caused_failure` |
| Time to restore | incident opened → resolved | `incidents` |
| Reliability (SLO) | do uploads parse successfully within 3 min? | `parse_events` |

## The two rules the numbers depend on

**1. A missing measurement is not a zero.** Every metric returns `null` (value *and*
bucket) when its window holds no rows. A change failure rate of "0%" computed over zero
deploys is not elite performance, it is an absence of evidence — and a dashboard that
renders a green badge for it is actively misleading. The frontend renders `null` as
"not measured yet", styled to recede rather than reassure.

**2. Failed deploys must report too.** The CI step that records a deployment runs under
`if: always()`. A change-failure rate fed only by successful runs is blind to exactly the
events it exists to count.

## Data model

Three tables in `public` (the api's schema), created by
`2026_08_30_000001_create_dora_tables.php`.

- **`deployments`** — one row per deploy of one service. Unique on
  `(service, actions_run_id)`: a retried workflow run must not read as a second deploy,
  which is the easiest way to inflate deployment frequency with your own plumbing.
- **`incidents`** — a production failure, open until resolved. `deployment_id` is nullable
  (not every incident comes from a deploy); setting it flips that deploy's
  `caused_failure`, which is what makes CFR self-maintaining.
- **`parse_events`** — one row per parse outcome. `match_id` is deliberately **not** a
  foreign key: telemetry outlives the match it describes, and deleting a demo must not
  rewrite delivery history.

Timestamps are stored as plain `timestamp` in UTC, matching every other table here.
Ingested values are normalized to UTC **at the boundary** (`RecordDeploymentRequest`),
because a CI runner in a non-UTC zone sends a real offset and casting that straight to the
column would keep the wall clock and silently move the instant — corrupting lead time,
which is a subtraction between two such instants.

## Ingestion

**Deploys — `POST /api/internal/deployments`.** Called by GitHub Actions, which has no user
and no session, so a shared secret is the whole identity. Guarded by `EnsureInternalToken`
(`X-Internal-Token` vs `DORA_INGEST_TOKEN`), compared with `hash_equals` and **failing
closed when unset**: forgetting to configure the token must deny everyone, never publish an
unauthenticated write path. Deliberately outside the `auth:sanctum` group, and throttled.

**Parse telemetry — no endpoint at all.** The worker already publishes `match.parsed` /
`match.failed` on `clutch_events` at exactly the moments this metric cares about, and
Laravel already subscribes. So telemetry is *one more reaction to a fact that was already
crossing the boundary*: `RecordParseSucceeded` / `RecordParseFailed`, registered like any
other handler. The worker gained one additive field (`duration_ms`) and no new transport,
no new secret, and no polling drain.

> This is the spec's Task C decided against its own two suggestions (an HTTP client in Go,
> or a second Redis list drained on a schedule). Both would have built a parallel path for
> information already in flight — and a metric that travels separately from the thing it
> measures is a metric that can disagree with it. See
> [notifications.md](notifications.md) for why adding a reaction is one class.

Telemetry handlers are tagged **last** in `AppServiceProvider`: recording a metric must
never sit in front of the write that makes a match visible to its owner. The listener
isolates handlers from each other, so a telemetry failure cannot cost a user their parse.

## Computation

`App\Services\Dora\MetricsCalculator`, one method per metric, all taking `(from, to)`.
Everything is computed on read — resolving an incident moves MTTR and CFR immediately,
with no redeploy and no recomputation step to forget.

Two choices worth stating:

- **Lead time and MTTR use the median, not the mean.** One commit that sat on a branch for
  a month would drag an average past the point where it describes anything real.
- **CFR's denominator is *successful* deploys.** A deploy that failed in the pipeline never
  reached users; counting it as a change failure would punish a pipeline for catching its
  own problem.
- **Open incidents are excluded from MTTR.** An outage still burning has no restore time,
  and treating "now" as its end makes MTTR improve every time you refresh the page.

Buckets follow the DORA / *State of DevOps* bands. CFR is the honest exception: the
research puts elite/high/medium all at 0–15%, so the code has two bands rather than
inventing three.

## API surface

| Method | Path | Auth | Purpose |
|---|---|---|---|
| POST | `/api/internal/deployments` | `X-Internal-Token` | record a deploy (CI) |
| GET | `/api/dora/metrics?window=30` | `can:admin` | all five metrics + trend |

`window` is clamped to 1–365: it is a plain query param, and an unbounded one lets any
admin request a full-table scan by typing a bigger number.

## Incidents

The one input with no automatic source — "this broke in a way that mattered" is a
judgement. Two commands rather than a UI to maintain:

```
php artisan dora:incident-open "uploads 500ing" --service=api --deployment=12
php artisan dora:incident-resolve 3
```

Opening with `--deployment` flips that deploy's `caused_failure`. Resolving an
already-resolved incident is a no-op: re-running must not stretch an outage that ended.

## Seeding and backfill

Two different problems, deliberately kept apart.

### Backfill — real deploy history from CI

`php artisan dora:backfill-deployments [--days=90] [--dry-run]`

Reconstructs `deployments` from the GitHub Actions runs API. Nothing is invented: the run
carries `head_sha`, `head_commit.timestamp`, `run_started_at`, `updated_at` and
`conclusion`, which is every column a deployment row needs. Run *metadata* is retained well
past the 90-day log expiry, so a long window is safe even though the logs are gone.

Three rules it enforces:

- **Only deploy workflows count.** `config('clutch.dora.deploy_workflows')` maps
  `deploy.api.yml` / `deploy.worker.yml` / `deploy.web.yml` to their service; everything
  else is skipped. This repo's `api.yml` and `worker.yml` are **test** workflows — importing
  them would turn deployment frequency into a count of how often CI ran, a number that
  looks like delivery and measures nothing of the kind.
- **Idempotent by the same rule as the live path.** It writes through
  `RecordDeploymentAction`, so the `(service, actions_run_id)` unique key deduplicates.
  Keying on the run id alone would collide across services sharing a workflow run.
- **Anything not `success` is a failed deploy.** `cancelled` and `timed_out` are deploys
  that didn't land, and CFR needs to see them.

The source sits behind the `DeploymentHistory` contract, so the CI provider is swappable
and tests never call GitHub.

> **Today this imports nothing, correctly.** The repo has 88 completed runs and zero deploy
> runs, because no deploy workflow has ever executed. The command says so explicitly rather
> than printing a bare "0 imported".

### Seeding — synthetic data for building the dashboard

`php artisan db:seed --class=DoraDemoSeeder` — then `php artisan dora:seed-clear` to remove it.

Parse telemetry and incidents **cannot** be backfilled: telemetry lives in the worker and
past outcomes were never recorded, and incidents are the manual table. So this is where
fabricated data earns its place — it lets the UI be built and demoed before the pipeline
exists. Volumes are shaped to be plausible (quieter weekends, ~7% pipeline failures, ~8% of
successful deploys causing an incident, ~95% of parses inside the SLO, and the most recent
incident left open so the "excluded from MTTR" path is exercised).

**The rule that keeps the two apart:** every seeded deploy carries a `seed-` prefixed
`actions_run_id`, and a synthetic incident may only ever blame a synthetic deploy. Randomly
marking real backfilled deploys as failures would make change failure rate a fiction while
looking entirely reasonable. `dora:seed-clear` keys on that marker (and on
`parse_events.match_id IS NULL`) so it removes exactly the invented rows and nothing CI
reported.

`DoraDemoSeeder` is deliberately **not** called from `DatabaseSeeder`: the test suite seeds,
and the metrics tests assert arithmetic that invented rows would break.

### Suggested order

1. Seed today, so the dashboard has something to render while the UI is built.
2. Implement the deploy step in `deploy.reusable.yml`; real rows start arriving from the
   `always()` ingestion step.
3. Once real runs exist, `dora:seed-clear` and then `dora:backfill-deployments` to sweep in
   the history. Clear before backfilling — mixed data is worse than either alone.

## Honest gaps

**There is no production environment and no deploy pipeline.** All seven existing workflows
are test-only. That means:

- **Reliability** and **MTTR** work today — parse outcomes are real, incidents are real.
- **Deployment frequency, lead time, and CFR** have no data source until something
  actually deploys. They will read "not measured yet", which is the correct answer.

`.github/workflows/deploy.reusable.yml` carries the two measurement points and the
`always()` reporting step, with the deploy step itself left **failing rather than
stubbed**. A stub that "succeeded" would post a stream of successful-deploy rows describing
nothing, and a dashboard showing Elite off invented data is worse than one admitting it has
nothing to measure. Implement the deploy, and the instrumentation around it starts
recording real rows with no further change.

## Related

- Why telemetry rides the event bus: [notifications.md](notifications.md)
- The parse outcomes it measures: [matches.md](matches.md)
- Wire contract for `duration_ms`: `contracts/README.md`
