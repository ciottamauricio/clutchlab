# Tactics board domain

## Status

Implemented in **Step 3**. The tactic (name + board state) is CRUD in Laravel and
**user-owned**; live collaborative editing is handled by the separate **`realtime`** Go
service over websockets. Both read/write the same `tactics` row (shared DB).

## Purpose

A collaborative tactics board: place and move pieces on a field, with everyone in the
same tactic seeing edits live. Persistence + ownership live here in Laravel; the
real-time transport (rooms, broadcast) is a different workload, so it's a different
service (see `docs/ARCHITECTURE.md` for why streaming ≠ batch).

## Ubiquitous language

- **Tactic** — a row in `tactics`: a named board owned by a user.
- **Board** — the tactic's state as JSON: `{ "pieces": [ … ] }`.
- **Piece** — one item on the board: `{ id, kind, x, y, label }`. `kind` is a marker type
  (e.g. `ct`, `t`, `smoke`, `flash`, `he`, `molly`); `x`/`y` are normalized `0..1`
  positions; `label` is optional text.
- **Room** — the realtime service's in-memory set of clients connected to one tactic id.

## Entities

### `tactics` (`app/Models/Tactic.php`)

| Field | Type | Written by | Notes |
|---|---|---|---|
| id | bigint | api | |
| user_id | fk users, cascade | api | owner (the creator) |
| name | string | api | |
| board | json, nullable | api (create) + realtime (edits) | `{ "pieces": [...] }` |
| created_at / updated_at | timestamps | api + realtime | |

## Rules & invariants

1. Creating a tactic sets `user_id` = the caller and an empty board `{ "pieces": [] }`.
2. Tactics are **user-owned**: only the owner may view, edit (CRUD or websocket), or delete;
   violations return **403** (`TacticPolicy`).
3. The board is **last-write-wins**: the realtime service persists whatever board a client
   sends and relays it to the others. There is no per-piece conflict resolution.
4. All endpoints and the websocket require authentication (Sanctum token).

## API surface (Laravel, `auth:sanctum`)

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/tactics` | list the caller's tactics |
| POST | `/api/tactics` | create (name → empty board) |
| GET | `/api/tactics/{tactic}` | fetch one (owner only) |
| PUT | `/api/tactics/{tactic}` | update name and/or board (owner only) |
| DELETE | `/api/tactics/{tactic}` | delete (owner only) |

## Real-time editing (the `realtime` service — cross-service contract)

Clients connect through nginx: `ws(s)://<host>/realtime/tactics/{id}?token=<sanctum token>`.

- The service validates the **Sanctum token against the shared `personal_access_tokens`
  table** (the same tokens Laravel issues) and checks the tactic is owned by that user;
  otherwise it closes the socket.
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
- 401 unauthenticated · 403 not the owner · 404 unknown tactic.

## Known limitations / later

- **User-owned only** — team sharing of tactics comes with the teams work later.
- **Last-write-wins** — no operational-transform / CRDT; fine for a small board.
- No link from a tactic to a parsed round yet (the roadmap's cross-service bonus).

## Related

- Why streaming is its own service: repo-root `docs/ARCHITECTURE.md`.
- The service: `realtime/` (see `worker/docs/ENGINEERING.md` for Go conventions).
- Ownership pattern mirrors [matches.md](matches.md).
