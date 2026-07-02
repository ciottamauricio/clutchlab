# Engineering guide — building & running Clutchlab (app-wide)

Cross-cutting conventions and the infrastructure every service shares. Sibling
[`ARCHITECTURE.md`](ARCHITECTURE.md) explains *why* the services are split; this explains
*how they're built and run together*.

Service-specific depth lives in each service's own guide:

- **Backend (Laravel):** [`../api/docs/ENGINEERING.md`](../api/docs/ENGINEERING.md)
- **Frontend (React):** [`../frontend/ENGINEERING.md`](../frontend/ENGINEERING.md)
- **Worker (Go):** no dedicated guide yet — see [`../worker/`](../worker/) (`main.go`, `parser.go`).

Domain business rules: [`../api/docs/domains/`](../api/docs/domains/).

## Principles (apply everywhere)

- **Thin edges, logic in the middle.** Controllers/routes and pages stay thin; logic lives
  in Action classes / services (backend) and hooks (frontend).
- **Validate only at the boundary** (the HTTP layer, external APIs). Trust internal code.
- **Depend on interfaces, not concretes.** Bind implementations in a provider; inject via the
  **constructor** — never `new` them inside a class.
- **Errors are codes, not sentences.** Every backend service returns codes
  (`demo.file_too_large`, `parse_failed_corrupt`); the frontend is the single place that turns
  codes into words.
- **Comment the non-obvious WHY only.** No comments that restate what the code does.
- **No dead code, no back-compat shims, no feature flags** unless genuinely needed.

## Infrastructure & runtime

### Topology (Docker Compose)

One `docker-compose.yml`. Only **nginx** is exposed to the host; everything else talks over
the private `clutchnet` bridge network **by service name**. (For dev convenience Postgres and
MinIO also publish ports so you can attach a GUI.)

| Service | Role | Host port |
|---|---|---|
| `nginx` | Gateway / reverse proxy | **8080** |
| `frontend` | React + Vite dev server | (internal 5173) |
| `api` | Laravel HTTP API | (internal 8000) |
| `worker` | Go demo parser (no inbound HTTP) | — |
| `postgres` | Primary datastore | 5432 (dev) |
| `redis` | Cross-language job queue | (internal 6379) |
| `minio` | S3-compatible object storage | 9000 / 9001 (dev) |
| `minio-init` | One-shot: creates the `demos` bucket, then exits | — |

Named volumes `pgdata` / `miniodata` persist data across restarts.

### Gateway (nginx)

- `/` → `frontend:5173`, forwarding the WebSocket upgrade so Vite HMR works.
- `/api/` → `api:8000` with **no trailing slash on `proxy_pass`**, so the `/api` prefix is
  preserved (adding a slash rewrites the path and Laravel 404s).
- `client_max_body_size 512M` — CS2 demos are large.

### Containers & the dev loop

- Each service owns its **own Dockerfile and dependency manifest** (`composer.json`,
  `package.json`, `go.mod`) — a monorepo without shared-tooling entanglement.
- Source is **bind-mounted** for live edits. Hot reload per language: Vite HMR (told the public
  port `8080` in `vite.config.js`), Go via **`air`**, PHP is interpreted (`php artisan serve`).
  The frontend's `node_modules` lives in an anonymous volume so the host dir doesn't shadow it.

### Config

- All config comes from Compose `env_file` (repo-root `.env`). Commit a `.env.example` with
  placeholders; **never** commit real secrets or `.env`.
- **Do not create `api/.env`** — `php artisan serve` forwards it and silently overrides Compose
  (e.g. flipping the DB to SQLite for web requests only).

### Networking footguns (read before debugging)

1. `proxy_pass http://api:8000;` — **no trailing slash** (preserves `/api`).
2. Inside containers use **service names** (`postgres`, `redis`, `minio`), never `localhost`.

### Toolchain pins (gotchas)

- Go stays on **1.24**; `minio-go` and `air` are pinned to 1.24-compatible versions (newer
  releases require Go 1.25).
- Composer platform is pinned to **php 8.3** in `api/composer.json` (the composer image runs 8.4).

## Cross-service contracts

- The parse queue payload `{ "match_id": int, "demo_key": string }` is a contract between
  Laravel and Go. **Change it on both sides in the same commit**, and update the domain doc.
  It's a plain Redis list (`demo_parse_jobs`) with an empty `REDIS_PREFIX` so both languages
  share the raw key — not Laravel's queue format.
- Codes-not-sentences holds across all services; the language boundary lives **only** in the
  frontend.

## Security

- Never commit secrets or `.env`. Always ship a `.env.example` with placeholders.
- All external-service config via environment variables.
- Rate-limit auth endpoints; 403 for ownership violations; `APP_DEBUG=false` in prod.

## Testing (philosophy)

Test at boundaries; mock external dependencies; never make real external calls in tests.
Per-service specifics live in the service guides:
[api](../api/docs/ENGINEERING.md#testing-api) (mock interfaces, in-memory SQLite) and
[frontend](../frontend/ENGINEERING.md) (Vitest + React Testing Library). Step 1 shipped
without tests; this is the standard going forward.

## Git

- Commit messages in the imperative mood; explain the **why**, not the what.
- One logical change per commit.
- Never commit generated files (vendor, node_modules, build output, generated specs).

## Adding a feature (cross-cutting flow)

1. Read — and update if rules change — the domain doc in [`../api/docs/domains/`](../api/docs/domains/).
2. Build the backend per the [api guide](../api/docs/ENGINEERING.md)
   (Form Request → Action → interfaces → thin controller → Resource).
3. Build the frontend per the [frontend guide](../frontend/ENGINEERING.md)
   (feature folder: `api.js` hook + components + thin page; map new codes in `lib/i18n.js`).
4. If a cross-service contract changes (e.g. the queue payload), update **both** sides **and**
   the domain doc in one commit.
5. Leave the domain doc reflecting the new rules.
