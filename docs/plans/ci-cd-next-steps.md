# CI/CD — what's next (saved plan)

Written 2026-07-16, after the test workflows landed. Current state and the ranked list
of what to add, with enough detail to execute each item cold. Execute roughly in order —
1–3 are one sitting; 4 is the first real CD rung; 5–7 are their own sessions.

## What CI does today (inventory)

| Workflow | Runs | Gap inside it |
|---|---|---|
| `api.yml` | composer install + phpunit (feature + contract tests, sqlite) | Pint installed but never run |
| `frontend.yml` | npm ci (+ musl→gnu twin fix) + vite build | `npm run lint` never run in CI; zero Vitest tests |
| `worker.yml` | go vet + go test | — |
| `notifier.yml` | go vet + go test | — |

Good bones already in place: per-service path filters (own dir + `contracts/**` + own
workflow file), PR triggers, checkout@v5 / setup-node@v5, test-only APP_KEY in api.yml,
the musl→gnu twin derivation for the Alpine-written lockfile.

## The gaps, ranked

### 1. `realtime` has no workflow — and no tests

A hole in the path-filter matrix: the websocket service can break and CI stays green.

- Add `.github/workflows/realtime.yml` — clone `worker.yml`, swap the paths/workdir.
- Write the first test so it isn't vet-only. Candidate: a table-driven test of whatever
  pure message-routing/board-merge logic exists in `realtime/` (find a `json.RawMessage`
  passthrough or hub broadcast decision to pin).

### 2. Wire the linters CI already owns

Both exist locally, neither runs in CI. One line each:

- `frontend.yml`: add `- run: npm run lint` before the build.
- `api.yml`: add `- run: ./vendor/bin/pint --test` after composer install.
- (worker/notifier already run `go vet`; optionally add `gofmt -l` checks.)

### 3. CI for `infra/` (terraform)

Extends the path-filter story to a fifth "service"; no cloud account needed.

- `.github/workflows/infra.yml`, paths `infra/**`:
  `terraform fmt -check -recursive` + `terraform init -backend=false` + `terraform
  validate` in both `infra/azure` and `infra/aws` (matrix over the two dirs).
- Uses `hashicorp/setup-terraform`.

### 4. Build + push images — the first CD rung

Nothing builds the Dockerfiles today (a broken one ships silently), and
`infra/README.md` explicitly says images-in-registry is CI's job.

- On push to main: build each service image, push to **GHCR** (free for public repos):
  `ghcr.io/<owner>/clutchlab-<service>:latest` + `:sha`.
- `docker/login-action` with the built-in `GITHUB_TOKEN` (`packages: write` permission),
  `docker/build-push-action` per service. PRs: build without push.
- Requires the missing artifact: a **multi-stage nginx image** that bakes the built
  frontend `dist/` (the Vite dev-server container has no cloud counterpart). This is the
  piece the Terraform skeletons are waiting on.
- Path-filter per service like the test workflows; consider one reusable workflow taking
  the service name as input instead of five copies.

### 5. Public-repo dependency hygiene

- `.github/dependabot.yml`: ecosystems `composer` (api), `npm` (frontend), `gomod`
  (worker, notifier, realtime), `github-actions` — weekly.
- Audit steps in existing workflows: `composer audit`, `npm audit --omit=dev`
  (decide: warn vs fail), `govulncheck ./...` in the Go workflows.

### 6. Frontend's first real tests (Vitest)

Conventions call for Vitest + Testing Library; `frontend.yml` has a comment reserving
the slot ("Vitest jobs slot in here when tests exist").

- `npm i -D vitest @testing-library/react @testing-library/jest-dom jsdom`.
- Natural first targets (pure functions, no mocking): `features/matches/format.js`
  (durations, month/day helpers, matchTeams ordering) and `features/trainings/nades.js`
  (URL scheme — pins the csnades path quirks, e.g. HE = `hegrenades`).
- Then `- run: npm test` in `frontend.yml`.

### 7. The walking-skeleton E2E (nightly, not per-push)

Study topic 13's promised endgame: one E2E, not ten.

- Separate workflow on `schedule:` (nightly) + `workflow_dispatch`.
- `docker compose up -d` on the runner, wait for health, then through nginx: register →
  login → upload a small fixture demo (commit a tiny/truncated `.dem` for this) → poll
  `/api/matches/{id}` until `parsed` → assert scoreboard non-empty.
- Budget ~10 min runtime; most expensive to keep green, hence nightly and last.

## Notes

- Keep the same-commit rule visible: any new workflow that consumes `contracts/**` must
  include it in its path filters.
- api.yml's APP_KEY is a test-only value that must decode to exactly 32 bytes.
- The lockfile is written in the musl dev container: any new frontend CI job that runs
  `npm ci` on a glibc runner needs the same gnu-twin step `frontend.yml` has.
