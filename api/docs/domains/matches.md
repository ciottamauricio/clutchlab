# Matches domain

## Purpose

The core domain. A user uploads a Counter-Strike 2 demo file (`.dem`); it is parsed
asynchronously by the Go worker; the resulting match summary and per-player
scoreboard are read back for a dashboard.

## Status

Implemented in Step 1 (vertical slice); made **user-owned** in Step 2, then **team-shareable**:
a match has an uploader and can be filed under a team so the whole team sees it (see
Authorization & ownership).

## Ubiquitous language

- **Demo** — a `.dem` recording of a CS2 match, uploaded by the user. Stored as an
  object in MinIO/S3, never in the database.
- **Match** — a row in `matches`: the tracked lifecycle of one uploaded demo. The
  Eloquent model is `GameMatch` because `Match` is a reserved word in PHP.
- **Parse** — the Go worker reading the demo and extracting stats.
- **Player stat** — one row per player per match in `match_player_stats`.
- **demo_key** — the S3 object key of the stored demo (`<uuid>.dem`); also the value
  that joins the web tier to the worker via the queue.

## Entities

### `GameMatch` — table `matches` (`app/Models/GameMatch.php`)

| Field | Type | Written by | Notes |
|---|---|---|---|
| id | bigint | api | |
| user_id | fk users, nullable | api | the uploader (null for legacy pre-auth rows) |
| team_id | fk teams, nullable | api | the team the match is shared with; null = private to the uploader |
| original_filename | string | api | client-provided name; display only |
| demo_key | string, unique | api | S3 object key `<uuid>.dem` |
| content_hash | string(64), nullable | api | sha256 of the uploaded demo's bytes; `unique(user_id, content_hash)` so a user can't upload the same demo twice. Null for legacy pre-hash rows. |
| status | string | api + worker | lifecycle code (see below) |
| error_code | string, nullable | worker | set only when `status = failed` |
| map_name | string, nullable | worker | e.g. `de_mirage` |
| score_ct / score_t | uint, nullable | worker | final round score per side |
| ct_name / t_name | string, nullable | worker | clan/team names (often empty in demos) |
| total_rounds | uint, nullable | worker | `score_ct + score_t` |
| tick_rate | float, nullable | worker | server tick rate, used to convert ticks to seconds |
| duration_seconds | float, nullable | worker | play time only: first round's freezetime-end to the last round's end (excludes warmup/post-game) |
| knife_round_winner | string(2), nullable | worker | stable side (`CT`/`T`) that won the pre-match knife round; `null` if none |
| parsed_at | timestamp, nullable | worker | set on success |
| played_at | timestamp, nullable | api | when the match was played (UTC), parsed from the filename's `YYYY-MM-DD__HHMM` prefix at upload; filterable/sortable |
| created_at / updated_at | timestamps | api + worker | |

### `MatchPlayerStat` — table `match_player_stats` (`app/Models/MatchPlayerStat.php`)

One row per (match, player). **Written exclusively by the worker.**

| Field | Type | Notes |
|---|---|---|
| match_id | fk → matches, cascade delete | |
| steam_id | string | 64-bit SteamID kept as text (JS precision). Unique with `match_id`. |
| name | string | player display name |
| team_side | string, nullable | `CT` / `T` from the last live round (sides swap at the half, so it's captured per round). Empty only if the player never appeared in a live round. |
| kills / deaths / assists / headshots | uint | tallied from Kill events |

No timestamps on this table.

## Lifecycle

```
queued ──▶ parsing ──▶ parsed
                   └──▶ failed   (error_code set)
```

- **queued** — set by the api the instant the demo is stored and the job enqueued.
- **parsing** — set by the worker when it pulls the job.
- **parsed** — terminal success: summary + player stats populated, `parsed_at` set,
  `error_code` cleared.
- **failed** — terminal failure: `error_code` explains why; summary/stats absent.

Transition rules:

- Only the **worker** moves a match out of `queued`.
- Terminal states (`parsed`, `failed`) are only produced by the worker.
- A `failed` (or crash-stuck `parsing`) match can be retried by re-enqueuing the same
  `{match_id, demo_key}` job; a successful reparse transitions it to `parsed` and
  **replaces** its stats (see invariant 4).

## Business rules & invariants

1. **One upload ⇒ exactly one `matches` row (status `queued`) and exactly one enqueued
   parse job.** (`app/Actions/UploadDemoAction.php`)
2. `demo_key` is unique and is the only identifier shared with the worker. The demo
   object lives in the `demos` bucket as `<uuid>.dem`.
3. The queue payload is the cross-language contract `{ "match_id": int, "demo_key": string }`.
   Change it on **both** sides in the same commit (Laravel `app/Queue/RedisParseQueue.php`,
   Go `worker/main.go` `Job`).
4. **Parsing is idempotent.** Reprocessing a match deletes its existing
   `match_player_stats` before inserting, so a re-delivered or retried job never
   doubles a player's stats. (worker `save()`)
5. The backend never emits user-facing prose. `status` and `error_code` are **codes**;
   the frontend localizes them.
6. `steam_id` is a **string** end-to-end (DB, API, JSON) to avoid 64-bit precision
   loss in JavaScript.
7. Summary fields (map, scores, rounds) are best-effort from the demo; `null` is valid
   until `status = parsed`.
8. A match that is not `parsed` exposes an **empty `players` array**. Consumers must
   treat non-`parsed` matches as "no stats yet", not "zero stats".
9. A match is uploaded by a user (`user_id`) and may be shared with a team (`team_id`). Any
   member of that team may view it; the uploader and upload-capable roles may delete/reparse
   it (see Authorization & ownership).
10. **The pre-match knife round is excluded from the stats.** It only decides side choice, so
    the worker strips its kills from the scoreboard and `kill_events` and records the winning
    side in `knife_round_winner`. Knife-round kills are detected as knife kills landing before
    the match's first gun kill (a tick test — round numbers are unreliable because the restart
    around the knife round can merge it with real round 1). Legitimate mid-game knife kills are
    kept. Opening-kill flags are recomputed on the surviving kills.
11. **The list marks the viewer's result.** `viewer_result` is `'win' | 'loss' | 'draw'`
    when the game was the viewer's: their own `steam_id` played in it (their seat decides
    the side), or **at least 4 members of one of their teams** were on the same side (a
    stack that queued together — per team, never pooled across teams). `null` otherwise,
    and always `null` off the list endpoint. Computed by
    `app/Actions/Matches/ComputeViewerResultsAction.php`; presentation-only, never stored.

## Validation (boundary) — `app/Http/Requests/UploadDemoRequest.php`

`demo` field:

- required
- must be a file
- extension must be `dem` (`extensions:dem`)
- max size `config('clutch.max_demo_kb')` = **512000 KB (500 MB)**

Emitted codes (never prose): `demo.required`, `demo.invalid`, `demo.wrong_extension`,
`demo.file_too_large`. A storage failure surfaces `demo.storage_failed`.

Infra limits that must stay **≥** this size: nginx `client_max_body_size` (512M) and
PHP `upload_max_filesize` / `post_max_size` (512M, set in `api/php.ini`).

## API surface

Base path `/api` (nginx preserves the prefix). **All match endpoints require `auth:sanctum`**.
"Visible" = the caller uploaded it or belongs to its team; write actions additionally need
upload rights (owner/igl) on the team, or being the uploader.

| Method | Path | Purpose | Notes |
|---|---|---|---|
| GET | `/matches` | list matches the caller can see, most recently **played** first (null `played_at` last, then by upload time) | own uploads + their teams' matches; **paginated 10/page** (`{ data, links, meta }`, `?page=`); optional `?player=` narrows to matches whose scoreboard contains that player name (case-insensitive substring; invalid value → `match.invalid_player_filter`); optional `?month=YYYY-MM` / `?day=YYYY-MM-DD` narrow to matches **played** in that calendar month / day (they intersect; undated matches only appear unfiltered; invalid values → `match.invalid_month` / `match.invalid_day`) |
| POST | `/matches` | upload a demo | multipart `demo`; optional `team_id` (owner/igl only); 201; throttled 30/min; rejects a demo the caller already uploaded (`match.duplicate`) |
| GET | `/matches/{match}` | match detail + players | visible only (403 otherwise); players by kills desc |
| GET | `/matches/{match}/kill-positions` | kill coordinates for the heatmap | visible only; see [heatmap.md](heatmap.md) |
| GET | `/matches/{match}/clutches` | clutches grouped by clutcher/round | visible only; each `{ round, size, killer_name, kills[] }` |
| GET | `/matches/{match}/demo` | download the stored `.dem` | visible only; streamed from storage via `DemoStorage::download` |
| POST | `/matches/{match}/reparse` | re-enqueue the stored demo | uploader or owner/igl; resets to `queued`; throttled 30/min; `ReparseMatchAction` |
| PATCH | `/matches/{match}` | move a match between private and a team | body `team_id` (int = share, null = private); manage bar (uploader/owner/igl); target team needs `uploadMatch` or `match.invalid_team`; `UpdateMatchTeamAction` |
| DELETE | `/matches/{match}` | delete a match | uploader or owner/igl (403 otherwise); 204; `DeleteMatchAction` |

Responses use `MatchResource` / `MatchPlayerStatResource` (wrapped in `data`).
`players` is present only when the relation is loaded (the detail endpoint).

**Reparse** (`ReparseMatchAction`) re-runs the parse on the demo already in storage — no
re-upload. It resets the match to `queued`, re-enqueues `{match_id, demo_key}`, and the worker
rewrites stats/events idempotently (delete-then-insert). This is how older matches backfill
newer analysis (e.g. kill positions added after they were first parsed).

**Delete** (`DeleteMatchAction`) removes the read-model docs (Meili) and the stored demo,
then deletes the row; `kill_events` / `round_events` / `match_player_stats` cascade via FK. It
is the only way to remove a match — there is no soft-delete.

## Error codes

**Status** (`status`): `queued`, `parsing`, `parsed`, `failed`.

**`error_code`** (set by the worker when `status = failed`):

| Code | Meaning |
|---|---|
| `parse_failed_download` | The demo object couldn't be fetched from storage. |
| `parse_failed_corrupt` | The demo couldn't be parsed — corrupt/unsupported (includes parser panics). |
| `parse_failed_timeout` | The parse exceeded the sandbox time limit (`PARSE_TIMEOUT_SECONDS`) — a pathological or hostile demo. |
| `parse_failed_memory` | The parse exceeded the sandbox heap limit (`PARSE_MEMORY_LIMIT_MB`). |
| `parse_failed_internal` | Parsing succeeded but persisting the results failed. |

The demo is **attacker-controlled input** fed to a native parser, so the worker sandboxes
it in layers (defense-in-depth):

1. **Panic recovery** — a demo that makes the parser panic becomes `parse_failed_corrupt`,
   never a crash.
2. **Resource limits** — a wall-clock timeout and heap ceiling, checked between demo
   frames (cooperative — no goroutine is force-killed), catch a demo that *hangs* or
   *exhausts memory* → `parse_failed_timeout` / `parse_failed_memory`.
3. **Process isolation** (`PARSE_ISOLATION=true`, on in prod) — the parse runs in a
   throwaway child process (the worker re-execs itself as `worker --parse-child`, demo
   bytes in on stdin, `ParseResult` JSON out on stdout). A hard crash, an OOM-kill, or a
   parser exploit is confined to that child; the parent sees a non-zero exit, fails the one
   job (`parse_failed_*`), and keeps running. The child gets an **empty environment** — no
   DB creds, no S3 keys — since it needs only the bytes on stdin. Off in dev (air has no
   stable binary to exec); the resource limits still apply in-process there.

Each layer catches what the one before it can't: recovery handles panics, limits handle
runaway resource use, isolation handles a crash or exploit. OS-level lockdown of the child
(no network, read-only filesystem) is the remaining rung.

**Upload / reassignment** (HTTP 422/500): the `demo.*` codes under Validation, plus
`match.invalid_team` (team the caller can't upload to), `match.upload_forbidden` (caller
isn't an uploader anywhere), and `match.duplicate` (caller has already uploaded this exact
demo — dedup is by sha256 of the file content, unique per uploader).

## Authorization & ownership

A match has an **uploader** (`user_id`) and optionally belongs to a **team** (`team_id`).
When it belongs to a team, **every member of that team can see it**; a match with no team is
private to its uploader. Enforced by `GameMatchPolicy`:

- `view` → the uploader, or any member of the match's team.
- `delete` / `reparse` → the uploader, or a member of the match's team whose role may upload
  (`owner` / `igl`). View-only roles (`player` / `coach`) cannot delete.
- The list endpoint returns the caller's own matches **plus** every match of the teams they
  belong to. The detail endpoint authorizes `view` and returns **403** otherwise (not 404).
- Uploading with a `team_id` requires `TeamPolicy::uploadMatch` (owner/igl) for that team;
  an invalid or forbidden team returns the code `match.invalid_team`. Omitting it uploads a
  private match.
- **Reassigning** a match's team (`PATCH /matches/{match}`) is authorized like `delete` (the
  manage bar); moving it *to* a team additionally requires `uploadMatch` on the target. Passing
  `team_id: null` makes it private again.
- The global `admin` passes all of the above (see [teams-auth.md](teams-auth.md)).
- All match endpoints require a valid bearer token (`auth:sanctum`).

Cross-match analytics (awards, search, player catalog, team stats) are still scoped to the
caller's **own uploads** for now; re-scoping them to team visibility is the next step.

## Known limitations (intentional — future steps)

- **Crash mid-parse:** if the worker dies after setting `parsing` but before a terminal
  state, the match stays `parsing` forever. There is no visibility timeout or
  dead-letter queue yet; recovery is a manual re-enqueue. (Reliability is a later lesson.)
- **Shared database** with the worker — deliberate; to be split later.
- Happy-path parsing depends on demoinfocs v5 and is verified against corrupt input,
  not yet against a real demo end-to-end.

## Related

- Cross-service reasoning: repo-root `docs/ARCHITECTURE.md`.
- Worker side of this domain: `worker/main.go`, `worker/parser.go`.
