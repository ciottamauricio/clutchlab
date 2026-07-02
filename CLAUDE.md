# Clutchlab

A polyglot, service-oriented app: upload a CS2 demo, parse it asynchronously, view
match stats. The subject being studied is the **boundaries between services**, not the
features. Solo learning project, monorepo.

## Read these before building

- **How to build & run** (app-wide: Docker, nginx, config, conventions): [`docs/ENGINEERING.md`](docs/ENGINEERING.md).
  Per-service depth: [`api/docs/ENGINEERING.md`](api/docs/ENGINEERING.md) and [`frontend/ENGINEERING.md`](frontend/ENGINEERING.md).
- **Why the services are split** (boundaries, async, polyglot queue): [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md)
- **What comes next** (ordered so each step teaches one concept): [`docs/ROADMAP.md`](docs/ROADMAP.md)
- **Per-service notes:** each service has its own `CLAUDE.md` —
  [`api/CLAUDE.md`](api/CLAUDE.md) and [`frontend/CLAUDE.md`](frontend/CLAUDE.md).
  The api's domain business rules live in [`api/docs/domains/`](api/docs/domains/) (read the
  relevant one before changing a domain); the frontend deep-dive is
  [`frontend/ENGINEERING.md`](frontend/ENGINEERING.md).

## Services

`nginx` (gateway) · `frontend` (React/Vite) · `api` (Laravel, CRUD) · `worker`
(Go, demo parsing) · `postgres` · `redis` (cross-language queue) · `minio` (S3 storage).
Only nginx is exposed to the host; services talk over the Docker network by **service
name**, never `localhost`.

## The rules that matter most

- Thin controllers → Form Requests → Action classes; external systems behind interfaces
  bound in a provider; constructor injection. (See the engineering guide.)
- Backends return **codes, not sentences**; the frontend is the only place that maps
  codes → localized text.
- The parse queue is a **plain Redis list** with JSON `{ "match_id", "demo_key" }`, shared
  with Go — change both sides in the same commit.
- Config comes from Compose `env_file` (repo-root `.env`); do **not** create `api/.env`.
