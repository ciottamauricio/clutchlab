# Splitting the shared database (saved plan)

Written 2026-08-09. The api and worker share one Postgres today — a deliberate shortcut
(study topic 08, `docs/ARCHITECTURE.md`). This plan splits it along a **data-ownership
line**, chosen approach **Option C first** (logical split via schemas + scoped DB roles),
staged so it later becomes a physical split (Option A) without a rewrite.

Execute in phases, in order. Phase 3 is later and optional, and leaves the app working
and green.

**Phases 1 and 2 must land together.** They read as separable but are not: phase 1 revokes
the worker's write on `matches`, which *breaks the worker* unless phase 2 has already moved
the status update onto an event. Splitting them across sittings leaves the tree red in
between. Phase 3 remains genuinely independent.

**Progress:** phases 1 + 2 landed — analytics schema, scoped roles (`clutch_app`,
`clutch_worker`), and the `match.parsed` / `match.failed` event handoff. Phase 3 not started.

## Why Option C (schemas + roles), not a second Postgres yet

A second Postgres container is real ops (a second server, backups, connection config,
health, a compose service) that is *not* the lesson. The lesson is the **ownership line**:
no cross-boundary foreign keys, no cross-boundary joins, no reaching into another
service's tables. Two schemas in one server with a role scoped to each enforces that line
for real — you hit every wall — at a fraction of the cost, and it sets up the physical
split as an increment rather than a big bang.

## Data safety: no reparse, and don't wipe anything

**No reparse is ever needed.** Reparsing re-runs the Go worker on the `.dem` files to
*regenerate* parsed output; the split only relocates existing rows and changes who may
write them. The parsed data's shape and content don't change, so there is nothing to
regenerate.

- **Phase 1** — `ALTER TABLE … SET SCHEMA` is a metadata rename, not a row move; instant,
  data untouched.
- **Phase 2** — changes only how *future* parses report status; existing parsed matches
  keep their rows and status.
- **Phase 3** — moves data *between servers*, but as a `pg_dump`/`COPY` migration, never a
  reparse.

The real risk in this kind of change is not the data model — it's **wiping the data during
a botched migration** (this project has wiped real Postgres twice; hence the sqlite
tripwire in `tests/TestCase.php`). So: every migration guards on the pgsql driver and has
a genuine reversible `down()`, and **take a `pg_dump` snapshot before running phase 1**
against real data. `SET SCHEMA` and `GRANT` are non-destructive by nature, but the snapshot
is the cheap safety net.

## The ownership line (from the real code)

The worker's DB surface today (`worker/internal/matches/store.go`) is small and clean:

| Table | api | worker | Owner after split |
|---|---|---|---|
| `matches` | creates row; owns identity, `user_id`, `team_id`, `played_at` | `UPDATE status/error_code` + writes parsed summary; reads `user_id`, `original_filename` | **api** (`app` schema) — shared truth |
| `match_player_stats` | reads (dashboards) | delete-then-insert (owns) | **worker** (`analytics` schema) |
| `kill_events` | reads (dashboards, search reindex) | delete-then-insert (owns) | **worker** (`analytics` schema) |
| `round_events` | reads (dashboards) | delete-then-insert (owns) | **worker** (`analytics` schema) |

Two frictions the split forces (this is the learning):

1. **The worker writes `matches` directly today** (`SetParsing`, `Fail`, and the summary
   `UPDATE` in `Save`). Once `matches` is the api's and the worker only owns `analytics`,
   the worker can no longer touch it. → resolved in **phase 2** by moving the status
   handoff onto the `match.parsed` / `match.failed` events the worker *already publishes*.
2. **`kill_events.match_id` / `match_player_stats.match_id` FK `matches(id)` across the
   line.** A cross-schema FK still works in one server (phase 1 keeps them), but it will
   NOT survive the physical split — so phase 1 documents them as "to drop at phase 3," and
   the app must already tolerate their absence (it does: the worker cascades manually via
   delete-by-match_id, and reads filter by match_id, not by JOIN-enforced integrity).

## Phase 1 — draw the line (logical split)

Goal: `analytics` tables live in their own schema; each service's DB role can touch only
its own schema. Everything still works; the wall is now enforced by Postgres.

1. **Migration: move the three analytics tables to an `analytics` schema.**
   `CREATE SCHEMA analytics; ALTER TABLE match_player_stats SET SCHEMA analytics;` (and
   `kill_events`, `round_events`). Postgres-only; guard on driver like the pgvector
   migration (sqlite tests have no schemas — keep them in the default schema there, or
   set `search_path` so unqualified names still resolve).
2. **Two DB roles, scoped by schema.**
   - `clutch_app` — usage on `public` (or `app`), no rights on `analytics`.
   - `clutch_worker` — usage on `analytics`; SELECT-only on `public.matches` (it still
     needs `user_id` / `original_filename` reads until phase 2 removes even those).
   Seed them in an init migration or a compose-run bootstrap; keep the dev superuser for
   migrations only.
3. **Point each service at its role.** New env: `DB_USERNAME`/`DB_PASSWORD` differ per
   service (api uses `clutch_app`, worker uses `clutch_worker`). Compose already injects
   per-service env; add `DB_*` overrides on the worker service block.
4. **Qualify the analytics table names.** The api's reads and the worker's writes now
   reference `analytics.kill_events` etc. — or set each role's `search_path` so the code
   is untouched. Prefer `search_path` to keep the diff small and the lesson about *roles*,
   not renames.
5. **Verify:** api dashboards still read stats; worker still parses and writes; the
   worker role is *denied* if it tries to write `matches` beyond its grant (prove the wall
   — a deliberate failing query in a scratch test, then removed). Search reindex
   (`analytics.kill_events`) still works.

Cost named: a cross-schema read grant (`clutch_worker` → `public.matches`) is still a
coupling — narrower than today, not gone. Phase 2 removes the worker's need for it.

## Phase 2 — the event-driven status handoff

Goal: the worker stops writing `matches` at all. The api owns the row end to end; the
worker reports outcomes as events (which it already publishes).

1. **Worker: drop `SetParsing`, `Fail`, and the summary `UPDATE matches` in `Save`.** The
   worker now writes only `analytics.*`. It already publishes `match.parsed` (with map +
   score) and `match.failed` (with `error_code`) on `clutch_events`.
2. **api: subscribe to `match.parsed` / `match.failed` and update `matches`.** The
   Laravel `events-listener` already consumes `clutch_events` (topic 15) — add handlers
   that set `status`/`error_code` and the parsed summary from the event payload. This is
   the same "one fact, a reaction where its data lives" pattern as the training email.
3. **Contract:** `match.parsed` must now carry everything the api needs to fill the row
   (map, scores, rounds, tick_rate, duration, knife winner…) — extend the fixture in
   `contracts/match_parsed.json` additively; publisher (worker) + subscriber (api) in the
   same commit, per the rule.
4. **`matches.status` lifecycle moves to the api.** "parsing" is now set by the api when
   it enqueues (or on a `match.parsing` event if you want the worker to signal start).
   Decide: is "parsing" worth an event, or does the api set it at enqueue time? (Simpler:
   api sets `queued`→`parsing` at enqueue; worker only reports terminal parsed/failed.)
5. **Drop the worker's SELECT grant on `public.matches`** — it no longer reads owner_id or
   filename (both must instead ride the job payload or the event). Check: `OwnerID` and
   `Filename` in the store are used for the denormalized `owner_id` on events and the demo
   filename in `match.parsed` — move those into the **parse job** (api already knows them
   at enqueue) so the worker needs zero `matches` access.

After phase 2 the worker touches **only** `analytics.*` — a clean, single-owner boundary.

## Phase 3 — physical split (optional, later)

Goal: `analytics` becomes its own Postgres. The ownership line is now also a network line.

1. New `analytics-postgres` compose service; worker points at it; api keeps `postgres`.
2. **Drop the cross-DB FKs** (`analytics.*.match_id` → `matches.id`) — impossible across
   servers. Integrity is now the app's job: the worker filters by `match_id`, and an
   orphan-analytics row (match deleted in api-db) is reaped by a subscriber to a
   `match.deleted` event, or tolerated + swept. This is the real "no FK across a boundary"
   lesson, made physical.
3. Dashboards that read both DBs stitch in the api layer (two queries, merge in code) —
   the "no cross-DB join" wall, felt for real.

## Study + docs to update alongside

- **Study topic 08** ("Shared database (for now)") — its "paid"/body already promises this
  split; update to reflect what's built vs. still deferred as phases land.
- Consider a **new topic** ("Splitting the store: ownership lines & the event handoff")
  once phase 2 lands — the event-driven status update is a strong standalone lesson.
- `docs/ARCHITECTURE.md` — the shared-DB section; `api/docs/domains/matches.md` — the
  status lifecycle now that the api owns it; `contracts/README.md` — the enriched
  `match_parsed.json`.

## The one-line summary of the lesson

Splitting a database is not a schema chore — it is choosing **who owns each fact**, and
then discovering that every convenience you had (FKs, joins, a direct UPDATE) was really a
loan against a shared database, now due. The events you already publish are how you pay it
back without a distributed transaction.
