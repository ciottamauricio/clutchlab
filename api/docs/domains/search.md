# Search domain

## Status

Implemented in **Step 4**. Full-text + faceted search over parsed events (kills and
rounds), backed by **Meilisearch** kept behind a **swappable interface** so the engine can
be replaced (e.g. Elasticsearch) without touching callers.

## Purpose

Answer questions the transactional tables can't cheaply serve — "my AWP opening kills on
Mirage", "rounds where CT won 5-on-3", "headshot kills with the deagle". This is a
**second read model** (CQRS): Postgres stays the source of truth; the search index is a
**projection** of it, updated after each parse. The gap between "parsed" and "searchable"
is **eventual consistency** you can watch.

## Decisions (Step 4)

- **Engine: Meilisearch, behind an interface.** Laravel depends on `App\Contracts\SearchIndex`;
  the Go worker depends on `internal/search.Indexer`. `Meilisearch*` implementations are bound
  in `AppServiceProvider` / wired in the worker's `main`. Swapping to Elasticsearch later means
  one new implementation on each side — callers don't change.
- **Index both kills and rounds** — two indexes, two document shapes.
- **Postgres is the source of truth.** The worker writes canonical `kill_events` /
  `round_events` rows, then projects them into Meilisearch. The index can be rebuilt from
  Postgres at any time (`php artisan search:reindex`).
- **Owner-scoped.** Every document carries `owner_id`; the api always filters searches to the
  authenticated user, so you only ever search your own matches.

## Ubiquitous language

- **Source of truth** — the `kill_events` / `round_events` Postgres tables.
- **Read model / projection** — the Meilisearch `kills` / `rounds` indexes, derived from the
  source of truth.
- **Eventual consistency** — a parsed match's events appear in search a moment after the
  worker finishes; a re-index reconciles any drift.

## Entities — source of truth (Postgres)

### `kill_events`

| Field | Type | Notes |
|---|---|---|
| id | bigint | Meilisearch primary key too |
| match_id | fk matches, cascade | |
| owner_id | bigint (denormalized from matches.user_id) | scoping |
| map | string | e.g. `de_mirage` |
| round | int | 1-based |
| killer_steam_id / killer_name | string | |
| victim_steam_id / victim_name | string | |
| assister_name | string, nullable | |
| weapon | string | e.g. `awp`, `ak47` |
| headshot | bool | |
| opening | bool | first kill of the round |
| side | string | killer's side (`CT`/`T`) |

### `round_events`

| Field | Type | Notes |
|---|---|---|
| id | bigint | Meilisearch primary key too |
| match_id | fk matches, cascade | |
| owner_id | bigint | scoping |
| map | string | |
| round | int | |
| winner | string | `CT`/`T` |
| reason | string | `elimination` / `bomb_exploded` / `bomb_defused` / `time` |
| ct_alive / t_alive | int | players alive per side at round end |
| ct_buy / t_buy | string | `eco` / `force` / `full` (heuristic on freeze-time equipment value) |

Both tables are rewritten idempotently per match (delete-by-match, re-insert), mirroring the
scoreboard's delete-then-insert.

## Read model — Meilisearch indexes

Two indexes, `kills` and `rounds`, whose documents mirror the rows above (the row `id` is the
Meili document id). Configured by `php artisan search:setup`:

- **kills** — filterable: `owner_id, match_id, map, weapon, headshot, opening, side,
  killer_team, round, killer_name, victim_name`; searchable: `killer_name, victim_name,
  weapon, map`; sortable: `round`. (`killer_name`/`victim_name` are both filterable *and*
  searchable: filter for an exact player, free-text for fuzzy.) `side` is the side **at the
  moment of the kill**; `killer_team` is the killer's **whole-match team** (their stable final
  side), which is what the per-match UI filters by — a team keeps its roster across the
  half-time swap, so `side` alone can't isolate one team's kills.
- **rounds** — filterable: `owner_id, match_id, map, winner, reason, ct_alive, t_alive, ct_buy, t_buy, round`;
  searchable: `map, reason`; sortable: `round`.

The document shape is a **contract between the worker (writer) and the api (reader)** — change
both sides in the same commit.

Kill docs also carry a few **display-only** fields that aren't filterable/searchable, so a
search result is self-sufficient for the reader UI without another lookup: `hitgroups` (body
damage → the hitgroup map), and `tick` + `tick_rate` + `demo` (→ the "watch in game"
`demo_gototick` command). `tick_rate`/`demo` are match-level, denormalized onto each kill so a
cross-match search still has them per hit (see [heatmap.md](heatmap.md)).

## Sync flow

```
worker parses demo
  ├─ writes match summary + player stats           (existing)
  ├─ writes kill_events / round_events to Postgres  (source of truth)
  └─ projects those rows into Meilisearch           (read model)
api  ── queries Meilisearch (owner-filtered) ──▶ results
```

- Indexing failure does not fail the parse; the match still becomes `parsed`. Drift is
  reconciled by `search:reindex` (reads Postgres → pushes to Meili via the interface).
- Deleting/re-parsing a match removes its docs (by `match_id` filter) before re-adding.

## API surface (Laravel, `auth:sanctum`)

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/search/kills` | search the caller's kills |
| GET | `/api/search/rounds` | search the caller's rounds |

Query params: `q` (free text) plus filter params matching the filterable attributes
(e.g. `weapon=awp&opening=1&map=de_mirage`, or `winner=CT&ct_alive=5&t_alive=3`). The api
**always** adds `owner_id = <auth user>` — never trust a client-supplied owner. Response is
`{ data: [...hits], total }`.

The same endpoints back the **per-match search** on the match dashboard: the UI pins
`match_id=<selected match>` and offers `killer_name` (drawn from that match's roster) + weapon
so a user can ask "this player's AWP kills in this match" without free-text guessing.

## Interfaces (the swap point)

- Laravel: `App\Contracts\SearchIndex` — `search()`, `indexDocuments()`, `deleteByMatch()`,
  `configure()`. Impl `App\Search\MeilisearchIndex` bound in `AppServiceProvider`.
- Worker: `internal/search.Indexer` — `IndexKills()`, `IndexRounds()`, `DeleteMatch()`. Impl
  `MeiliIndexer`.

To move to Elasticsearch: add `ElasticsearchIndex` / `ElasticIndexer` and rebind — no caller,
controller, or worker-logic changes.

## Error codes

- 401 unauthenticated · results are always owner-scoped so cross-user access can't happen.
- Search-engine unavailability surfaces `search.unavailable` (the api degrades, it doesn't 500).

## Known limitations / later

- **Eventual consistency**: a just-parsed match is searchable a beat later; acceptable and the
  point of the lesson.
- **Re-index is manual** (`search:reindex`) — no automatic drift detection.
- Owner-scoped only (no team-shared search yet).
- `buy_type` is a rough equipment-value heuristic.
- **Round counting is approximate**: `winner` is the *side* that won each round (sides swap
  at the half, so per-side tallies won't equal a team's final score), and knife/restart
  rounds can slip in — so `round_events` may exceed the scoreboard's round count by one or two.

## Related

- Why a separate read model: repo-root `docs/ARCHITECTURE.md`.
- Producer of the events: `worker/` (see `worker/docs/ENGINEERING.md`).
- Leads into Step 5 (the parser emitting events that multiple consumers react to).
