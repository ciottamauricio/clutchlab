# Trainings domain

## Purpose

Scheduled practice: a team books a time, attaches the tactics it will drill, and names
the players expected to attend. CRUD in Laravel — this domain stretches modeling, not
architecture (docs/ROADMAP.md, "stays in Laravel").

## Status

**Implemented** (schema → API → UI → event). Scheduling publishes `training.scheduled`
on the events channel — the first Laravel-published cross-service event (see Events).

## Ubiquitous language

- **Training session** — a row in `training_sessions`: one team, one scheduled time,
  a set of tactics to practice, a roster of expected players.
- **Roster** — the users expected at the session (`training_session_user`). Each entry
  carries an **RSVP**: `null` (no answer yet) / `in` / `out`, answered by that player.
- **Assignment** — one piece of pre-class homework (`training_assignments`): a player,
  a map, a nade type; `done_at` when the player marks it studied.
- **Session tactics** — the tactics to drill (`training_session_tactic`), each linking
  to the collaborative board.

## Entities

### `TrainingSession` — table `training_sessions`

| Field | Type | Notes |
|---|---|---|
| id | bigint | |
| team_id | fk teams, cascade | sessions always belong to a team — no private trainings |
| created_by | fk users, nullOnDelete | who scheduled it; survives the scheduler leaving |
| title | string | e.g. "A-executes + retakes" |
| notes | text, nullable | free-form agenda |
| scheduled_at | timestamp | **UTC**; the frontend localizes (same rule as `played_at`) |
| duration_minutes | uint, nullable | display only |
| canceled_at | timestamp, nullable | cancellation without a status machine; null = on |
| created_at / updated_at | timestamps | |

Pivots: `training_session_tactic` (unique pair, cascade both ways) and
`training_session_user` (unique pair, cascade both ways).

### `TrainingAssignment` — table `training_assignments`

Pre-class homework: "player X studies {map} {nade type} before this session."

| Field | Type | Notes |
|---|---|---|
| training_session_id | fk, cascade | |
| user_id | fk users, cascade | the student — must be on the session's roster |
| map | string(32) | opaque label (same rule as `tactics.map`); the frontend owns the map list |
| nade_type | string(16) | closed enum: `smoke` / `molotov` / `flashbang` / `he` |
| done_at | timestamp, nullable | set by the **assignee only** — "studied it" |

Unique `(training_session_id, user_id, map, nade_type)`; creating an existing
assignment is idempotent (firstOrCreate), never an error.

**The backend stores the meaning, never a URL.** The study link (csnades.gg today)
is derived in the frontend from `map + nade_type` — the same boundary rule as codes
vs. sentences: semantics in the API, rendering (including external-site URL schemes)
in the client. Swapping study sites is a one-module frontend change.

## Business rules & invariants

1. **A session always belongs to a team.** A solo practice needs no roster or schedule —
   there is deliberately no private variant.
2. **Roster ⊆ team members.** Every user attached must be a member of the session's team
   at attach time; violations return `training.invalid_player`. (Members who later leave
   the team keep their historical attendance rows — history is not rewritten.)
3. **Tactics must be visible to the team**: shared with that team, or owned by one of its
   members; violations return `training.invalid_tactic`.
4. Past `scheduled_at` values are allowed — a session record doubles as history. A session
   with `canceled_at` set is canceled, not deleted.
5. **Who schedules**: the team-scope ability `training.manage` (create/update/cancel/
   delete), granted by default to `owner`, `igl`, **and `coach`** — the role that is
   view-only on matches but whose job is running practice. Viewing is plain team
   membership (like tactics), not a grantable ability.
6. Codes, not sentences: `training.invalid_team`, `training.invalid_time`,
   `training.invalid_player`, `training.invalid_tactic`, `training.invalid_assignee`,
   `training.invalid_nade`. (RSVP by a non-roster caller is an authorization matter —
   403 — not a validation code.)
7. **Self-only actions**: RSVP (`in`/`out`) and marking an assignment done belong to the
   player themselves — `training.manage` does not grant them. The coach assigns and
   invites; only the student can say "I'll be there" or "studied it".
8. Assignment assignees must be on the session's roster (homework is for attendees).

## API surface (Phase 2)

All under `auth:sanctum`; "visible" = member of the session's team; 403 otherwise.

| Method | Path | Purpose |
|---|---|---|
| GET | `/trainings` | sessions of all the caller's teams, soonest first |
| POST | `/trainings` | schedule (title, team_id, scheduled_at, tactic_ids[], player_ids[]) — needs `training.manage` on the team |
| GET | `/trainings/{session}` | detail with tactics + roster |
| PATCH | `/trainings/{session}` | edit fields / replace tactics / replace roster / set `canceled_at` |
| DELETE | `/trainings/{session}` | remove entirely |
| PATCH | `/trainings/{session}/rsvp` | body `{ "going": bool }` — caller answers their own invite (roster members only) |
| POST | `/trainings/{session}/assignments` | `{ user_id, map, nade_type }` — `training.manage`; idempotent |
| PATCH | `/trainings/{session}/assignments/{assignment}` | `{ "done": bool }` — the assignee only |
| DELETE | `/trainings/{session}/assignments/{assignment}` | `training.manage` |

## Events (Phase 4)

Creating a session fires the in-process domain event `TrainingScheduled`; a listener
publishes `training.scheduled` on `clutch_events` via the `EventBus` contract — Laravel's
first turn as event **publisher** (contract fixture `contracts/training_scheduled.json`,
producer test on the PHP side, consumer test in the notifier). The Action itself stays
side-effect-free; whether this indirection spreads to other domains is decided after
living with it here.

## Structure note

This domain starts the **domain-subfolder convention**: `app/Actions/Trainings/`,
`app/Http/Requests/Trainings/`. Existing domains migrate when next touched
(api/docs/ENGINEERING.md).

## Related

- Authorization model: [teams-auth.md](teams-auth.md)
- Tactics being attached: [tactics.md](tactics.md)
