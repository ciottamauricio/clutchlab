# Tactics board domain

## Status

Implemented in **Step 3**. The tactic (name + board state) is CRUD in Laravel, owned by
its creator and optionally **shared with a team** (every member may open and co-edit it);
live collaborative editing is handled by the separate **`realtime`** Go service over
websockets. Both read/write the same `tactics` row (shared DB).

## Purpose

A collaborative tactics board: place and move pieces on a field, with everyone in the
same tactic seeing edits live. Persistence + ownership live here in Laravel; the
real-time transport (rooms, broadcast) is a different workload, so it's a different
service (see `docs/ARCHITECTURE.md` for why streaming ≠ batch).

## Ubiquitous language

- **Tactic** — a row in `tactics`: a named board owned by a user.
- **Board** — the tactic's state as JSON: `{ "pieces": [ … ], "lines": [ … ] }`. The shape
  is **owned by the frontend**: the realtime service persists and relays it as opaque JSON,
  so new board content (like `lines` was) is a frontend-only change.
- **Piece** — one item on the board: `{ id, kind, x, y, label }`. `kind` is a marker type
  (e.g. `ct`, `t`, `smoke`, `flash`, `he`, `molly`); `x`/`y` are normalized `0..1`
  positions; `label` is optional text.
- **Line** — one freehand stroke: `{ id, color, points: [[x, y], …] }`, normalized `0..1`
  pairs in draw order. `color` is a frontend theme-token name (`accent`/`ct`/`t`/`danger`),
  not a hex. Absent on boards saved before the pen existed — readers must default `lines`
  to `[]` and `color` to `accent`.
- **Room** — the realtime service's in-memory set of clients connected to one tactic id.

## Entities

### `tactics` (`app/Models/Tactic.php`)

| Field | Type | Written by | Notes |
|---|---|---|---|
| id | bigint | api | |
| user_id | fk users, cascade | api | owner (the creator) |
| team_id | fk teams, nullable, nullOnDelete | api | the team the tactic is shared with; null = private to the owner. Deleting a team un-shares its tactics. |
| name | string | api | |
| map | string(32), nullable | api | which map the board draws on (e.g. `de_mirage`); null = plain field. An opaque label to the backend — the frontend owns the map list and radar images (`public/radars/`), so an unknown value just renders without a radar. |
| board | json, nullable | api (create) + realtime (edits) | `{ "pieces": [...], "lines": [...] }` |
| created_at / updated_at | timestamps | api + realtime | |

## Rules & invariants

1. Creating a tactic sets `user_id` = the caller and an empty board `{ "pieces": [] }`.
2. **Ownership & sharing** (`TacticPolicy`): the owner may do everything. A tactic shared
   with a team may be **viewed and edited** (CRUD or websocket) by every member of that
   team — a shared strat is a collaboration surface. **Delete and re-share stay with the
   owner.** Violations return **403**. Access is membership-based, not a grantable ability
   (the finer tactics gating noted in [teams-auth.md](teams-auth.md) known limitations).
3. The board is **last-write-wins**: the realtime service persists whatever board a client
   sends and relays it to the others. There is no per-piece conflict resolution.
4. All endpoints and the websocket require authentication (Sanctum token).

## API surface (Laravel, `auth:sanctum`)

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/tactics` | list visible tactics: own + the caller's teams' (`Tactic::visibleTo`) |
| POST | `/api/tactics` | create (name + optional map → empty board) |
| GET | `/api/tactics/{tactic}` | fetch one (owner or team member) |
| PUT | `/api/tactics/{tactic}` | update name, map and/or board (owner or team member) |
| PATCH | `/api/tactics/{tactic}` | move between private and a team — body `team_id` (int = share, null = private); owner only; target must be a team the owner belongs to (`tactic.invalid_team`); `UpdateTacticTeamAction` |
| DELETE | `/api/tactics/{tactic}` | delete (owner only) |

`TacticResource` ships `team` (when loaded), `owner`, and `can.delete` so the UI knows
whether to offer the share/delete controls; the server still enforces every request.

## Real-time editing (the `realtime` service — cross-service contract)

Clients connect through nginx: `ws(s)://<host>/realtime/tactics/{id}?token=<sanctum token>`.

- The service validates the **Sanctum token against the shared `personal_access_tokens`
  table** (the same tokens Laravel issues) and checks the caller may access the tactic —
  owner **or member of its team** (`store.TacticAccess`, the mirror of `TacticPolicy`;
  keep the two in sync); otherwise it closes the socket.
- Messages are JSON with a `type`:
  - server → client on connect: `{ "type": "snapshot", "board": { … } }` (persisted board)
  - client → server on edit: `{ "type": "update", "board": { … } }`
  - server → other clients: `{ "type": "update", "board": { … } }` (relayed)
  - server → clients on join/leave: `{ "type": "presence", "count": N }`
- On each `update` the service **persists** the board to `tactics.board` and broadcasts it
  to everyone else in the room.

This message shape is a contract between the `realtime` service and the frontend — change
both sides in the same commit.

## Error codes

- `tactic.name_required` — missing name on create/update.
- `tactic.invalid_team` — share target isn't a team the owner belongs to.
- 401 unauthenticated · 403 not allowed (not owner / not a member) · 404 unknown tactic.

## Known limitations / later

- **Last-write-wins** — no operational-transform / CRDT; fine for a small board.
- No link from a tactic to a parsed round yet (the roadmap's cross-service bonus).

## Related

- Why streaming is its own service: repo-root `docs/ARCHITECTURE.md`.
- The service: `realtime/` (see `worker/docs/ENGINEERING.md` for Go conventions).
- Ownership pattern mirrors [matches.md](matches.md).
