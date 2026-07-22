# Clutchlab API (Laravel) — notes for AI & contributors

The CRUD/web tier of Clutchlab. Heavy compute (CS2 demo parsing) lives in the Go
`worker`; this service accepts uploads, owns the relational data, and exposes the
HTTP API.

- *How* to build in **this service** (Laravel layers + recipe): [`docs/ENGINEERING.md`](docs/ENGINEERING.md)
- App-wide build & run (Docker, nginx, config, contracts): repo-root [`../docs/ENGINEERING.md`](../docs/ENGINEERING.md)
- *Why* the services are split: repo-root [`../docs/ARCHITECTURE.md`](../docs/ARCHITECTURE.md)

## Before changing a domain, read its doc

Business rules, invariants, lifecycle, and error codes for each domain live in
[`docs/domains/`](docs/domains/). **Read the relevant file before implementing or
changing behavior in that domain, and update it in the same change when a rule
changes.** The code follows the doc, not the other way around.

| Domain | Status | Doc |
|--------|--------|-----|
| Matches — demo upload + parsing | Implemented (Step 1) | `docs/domains/matches.md` |
| Teams & Auth — roles, ownership, team-shared matches, master admin | Implemented | `docs/domains/teams-auth.md` |
| Tactics board | Implemented (Step 3) | `docs/domains/tactics.md` |
| Search | Implemented (Step 4) | `docs/domains/search.md` |
| Kill heatmap — kill positions on radar | Implemented | `docs/domains/heatmap.md` |
| Awards — cross-match superlatives | Implemented | `docs/domains/awards.md` |
| Notifications | Implemented (Step 5) — pub/sub; Go notifier + Laravel events-listener | `docs/domains/notifications.md` |
| Trainings — scheduled practice (time + tactics + roster) | API implemented; UI + event next | `docs/domains/trainings.md` |
| Analyst — RAG Q&A over the caller's matches | Implemented | `docs/domains/analyst.md` |

## API conventions (non-negotiable)

- Controllers are thin: validate in a **Form Request**, delegate to an **Action
  class** (one per operation), return a **Resource**. No business logic in controllers.
- External integrations sit behind an **interface** in `app/Contracts/`, with the
  concrete implementation bound in `app/Providers/AppServiceProvider.php`. Type-hint
  the interface, never the concrete class.
- The backend returns **error codes, not sentences** (e.g. `demo.file_too_large`,
  status `parse_failed_corrupt`). The React frontend maps codes → localized text.
  Never return user-facing prose from here.
- Ownership violations return **403**, not 404 (once auth exists).

## Runtime facts that bite

- Config comes from Docker Compose `env_file` (repo-root `.env`). **Do not create
  `api/.env`** — `php artisan serve` forwards it and silently overrides Compose
  (e.g. flipping the DB back to SQLite for web requests only).
- DB is Postgres (`pgsql`), **shared with the worker** for now — deliberate; to be
  split in a later step (see architecture doc).
- The parse queue is a **plain Redis list** (`demo_parse_jobs`) with JSON payload
  `{ "match_id": int, "demo_key": string }`, shared with Go. `REDIS_PREFIX` is empty
  so both sides use the raw key. This is **not** Laravel's queue system.
- Composer platform is pinned to php 8.3 in `composer.json`.
