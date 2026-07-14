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
- **Roster** — the users expected at the session (`training_session_user`). Expected,
  not confirmed: RSVP is a deliberate later addition (additive pivot column).
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
   `training.invalid_player`, `training.invalid_tactic`.

## API surface (Phase 2)

All under `auth:sanctum`; "visible" = member of the session's team; 403 otherwise.

| Method | Path | Purpose |
|---|---|---|
| GET | `/trainings` | sessions of all the caller's teams, soonest first |
| POST | `/trainings` | schedule (title, team_id, scheduled_at, tactic_ids[], player_ids[]) — needs `training.manage` on the team |
| GET | `/trainings/{session}` | detail with tactics + roster |
| PATCH | `/trainings/{session}` | edit fields / replace tactics / replace roster / set `canceled_at` |
| DELETE | `/trainings/{session}` | remove entirely |

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
