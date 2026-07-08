# Teams & Auth domain

## Status

Implemented in **Step 2** (backend). Frontend auth UI follows in the same step.

## Purpose

Authentication (register/login), users, and teams with **team-scoped roles**
(owner / igl / player / coach). Introduces the authorization layer the app dodged in
Step 1: matches become user-owned and access is enforced.

## Decisions (Step 2)

- **Match ownership: uploader + optional team.** `matches.user_id` = the uploader;
  `matches.team_id` = the team it's shared with (optional). Any team member may **view** a
  team match; the uploader and upload-capable roles (owner/igl) may **manage** it. A match
  with no team is private to its uploader. Non-visible → **403**.
- **Auth: Sanctum API tokens (Bearer).** Register/login return a plain-text token; the SPA
  sends `Authorization: Bearer <token>`. Guard: `auth:sanctum`.

## Ubiquitous language

- **User** — an authenticated account. Owns matches; belongs to teams; carries a global role.
- **Team** — a named group of users. Membership carries a role.
- **Global role** — a user's platform-wide role: `member` (default) | `admin`. The `admin` is
  the **master admin** who manages every user and passes every authorization check. Distinct
  from the per-team role below. Stored on `users.role`; not mass-assignable (an admin sets it).
- **Team role** — a user's role *within a team*: `owner` | `igl` | `player` | `coach`. Roles are
  per-team (a user can be owner of one team and a player in another). Today only `owner`
  grants team-management permission.
- **Linked SteamID** — an account's own SteamID64 (`users.steam_id`), admin-assigned and
  optional. Bridges the login account to its demo stats (keyed by SteamID64) so a user can see
  their own numbers. Not every account has one (a coach/analyst never played). Distinct from
  `team_players`, which says a SteamID *plays for a team*, not that it *is an account*.
- **Token** — a Sanctum personal access token; the bearer credential for the API.

## Entities

### `users` (Laravel default + additions)

- `id`, `name`, `email` (unique), `password` (hashed), **`locale`** (default `en`), timestamps.
- `locale`: the user's language choice, persisted across devices (frontend i18n seam).
- **`role`** (default `member`): global role, cast to `App\Enums\UserRole` (`member` | `admin`).
  **Out of `Fillable`** — register/login can never set it; an admin assigns it deliberately.
- **`steam_id`** (nullable, unique, string): the account's own SteamID64. Also out of `Fillable`.
- Relations: `matches` (hasMany), `teams` (belongsToMany via `team_user`, `withPivot('role')`).

### `teams`

- `id`, `name`, timestamps.
- Relations: `members` (belongsToMany `User` via `team_user`, `withPivot('role')`).

### `team_user` (pivot)

- `id`, `team_id` (fk cascade), `user_id` (fk cascade), `role` (string), timestamps.
- `unique(team_id, user_id)` — a user has at most one role per team.

### `team_players` (roster pivot)

- `id`, `team_id` (fk cascade), `steam_id` (string — SteamID64), `nickname` (nullable), timestamps.
- `unique(team_id, steam_id)` — a player is on a team's roster at most once.
- **Distinct from `team_user`.** `team_user` is *app-login membership* (who may edit the team).
  `team_players` is the *in-game roster* (whose demo stats belong to the team). A rostered
  player usually has **no account** — they're just a SteamID64 seen in an uploaded demo. The
  two never join; a member may or may not also be a rostered player.
- Identity is the **SteamID64**, never the demo name: names drift (clan tags, emojis) but the
  id is stable. `nickname` is an optional owner-set label; display falls back to the most
  recent demo name.

## Players catalog & team stats

The roster is built by picking from the **player catalog** — every distinct player seen across
the caller's own matches — and stats are aggregated from `kill_events` for the rostered ids.

- **Catalog** (`GET /players`): derived from `match_player_stats` joined to owned `matches`,
  grouped by `steam_id`. Returns `{ steam_id, name (most recent), match_count }`. **No separate
  players table** — the catalog is a read projection over match data.
- **Team stats** (`GET /teams/{team}/stats`): a Postgres aggregation over `kill_events`
  (`ComputeTeamStatsAction`), scoped to the caller via `kill_events.owner_id` and the roster's
  `steam_id` set. Per player: `games` (matches the player appears in), `kills`, `deaths`, `kd`,
  `hs_pct`, `entry_kills` (opening kills), `first_deaths` (rounds the player was the opening
  victim), `clutches` (distinct won 1vN rounds). Analytics live in Postgres; Meilisearch stays
  the text-search read model only.
- **Player clutches** (`GET /players/{steamId}/clutches`): every won 1vN the player made across
  the caller's matches, grouped by `(match, round)` — the same shape as
  [matches.md](matches.md)'s `clutches`, but each card carries its own `map`/`demo`/`tick_rate`
  so the "watch in game" jump works across matches. Powers the clickable clutch cell on the
  stat board.
- Stats need `kill_events.victim_steam_id` (present since the Step 4 search read model) so
  death-side metrics key on the victim's stable id, not the name.

### `matches` (addition)

- `user_id` (fk `users`, nullable, indexed) — the uploader. Legacy pre-auth rows are null.
- `team_id` (fk `teams`, nullable, indexed, `nullOnDelete`) — the team the match is shared
  with; null = private to the uploader. Deleting a team un-shares its matches (sets null),
  it doesn't delete them.

## Roles & permissions

| Action | Who |
|---|---|
| Create a team | any authenticated user (becomes its `owner`) |
| View a team + members | any member |
| Add / remove members, change roles, rename/delete team | members with role `owner` |
| Add / remove roster players, view team stats¹ | members with role `owner` (view stats: any member) |
| Upload a match to a team | members with role `owner` or `igl` |
| View a team's match | any member of that team |
| Delete / reparse a match | the uploader, or the team's `owner` / `igl` |
| Everything above, on any resource | global `admin` (master admin) |

`igl` / `player` / `coach` are descriptive roles for now; they gain permissions when later
features attach behavior to them.

The global **`admin`** is orthogonal to team roles: it isn't a team owner, it overrides every
policy. Implemented as a single `Gate::before` short-circuit (returns `true` for admins, `null`
otherwise so non-admins fall through to the ordinary policy) — see Authorization below.

## Rules & invariants

1. **Register** creates a user and issues a token; **login** verifies credentials and issues a
   token. Both return `{ user, token }`.
2. Auth failures return the code **`auth.invalid_credentials`** (never prose).
3. Every non-auth endpoint requires a valid bearer token (`auth:sanctum`); missing/invalid → **401**.
4. **Creating a team** attaches the creator as a member with role `owner` in the same operation.
5. A user has at most one role per team (unique pivot). Adding an existing member returns
   **`team.already_member`**.
6. **Ownership:** a user may view any match of a team they belong to and manage matches they
   uploaded (or that belong to a team where they're owner/igl); violations return **403** (not 404).
7. Match upload sets `user_id` = the authenticated user and optionally `team_id`; the match
   list returns the caller's own matches plus every match of the teams they belong to.
8. Auth endpoints are rate-limited.

## Validation (boundary)

- **Register:** `name` required; `email` required/email/unique; `password` required/confirmed/min:8.
- **Login:** `email` required/email; `password` required.
- **Create team:** `name` required.
- **Add member:** `email` required/exists; `role` required/in `owner,igl,player,coach`.
- **Add roster player:** `steam_id` required/string; `nickname` nullable/string. Re-adding an
  existing `steam_id` is idempotent (updates the nickname), so there's no "already rostered" error.

¹ Roster and stats are scoped to the **caller's own matches** (`kill_events.owner_id`): the board
shows how the rostered ids performed in demos you uploaded.

## API surface

**Public**

| Method | Path | Purpose |
|---|---|---|
| POST | `/api/register` | create account + token |
| POST | `/api/login` | authenticate + token |

**Protected (`auth:sanctum`)**

| Method | Path | Purpose |
|---|---|---|
| POST | `/api/logout` | revoke the current token |
| GET | `/api/me` | current user |
| GET/POST/GET | `/api/matches…` | uploader + team-shared (see [matches.md](matches.md)) |
| GET | `/api/players` | catalog of players seen across my matches |
| GET | `/api/players/{steamId}/clutches` | a player's won clutches across my matches |
| GET | `/api/teams` | teams I belong to |
| POST | `/api/teams` | create a team (I become `owner`) |
| GET | `/api/teams/{team}` | team + members + roster (member only) |
| GET | `/api/teams/{team}/stats` | roster stat board (member only) |
| POST | `/api/teams/{team}/members` | add a member by email + role (owner only) |
| DELETE | `/api/teams/{team}/members/{user}` | remove a member (owner only) |
| POST | `/api/teams/{team}/players` | add a player to the roster by steam_id (owner only) |
| DELETE | `/api/teams/{team}/players/{steamId}` | remove a rostered player (owner only) |

## Authorization (policies)

- `Gate::before` (in `AppServiceProvider::boot`): a global `admin` passes every check.
- `GameMatchPolicy`: `view` → uploader or any member of `match.team_id`; `delete` / `reparse`
  → uploader or an `owner`/`igl` of `match.team_id`.
- `TeamPolicy`: `view` → user is a member; `manageMembers` / `update` / `delete` → the user's
  role in that team is `owner`; `uploadMatch` → role `owner` or `igl`.

## Error codes

- `auth.invalid_credentials` — bad email/password (login).
- `match.invalid_team` — upload `team_id` isn't a team the caller may upload to (owner/igl).
- `team.already_member` — the user is already in the team.
- `team.steam_id_required` — no player selected when adding to the roster.
- 401 unauthenticated · 403 authorization · 422 validation (field codes).

## Known limitations / later

- Roles beyond `owner`/`igl` don't grant permissions yet (`igl` gained match-upload rights).
- No invitation/acceptance flow — an owner adds existing users directly by email.
- **Cross-match analytics** (awards, search, player catalog, team stats) are still scoped to
  the caller's own uploads, not their teams' matches — re-scoping is the next step.
- A match belongs to at most one team; there's no re-assigning a match's team after upload yet.
- Removing the last `owner` of a team isn't prevented.
- **Global role & `steam_id` are set by hand** (tinker) for now — the admin UI to list users,
  set the global role, and assign a SteamID is a later step. No self-service Steam linking.
- No guard against demoting the last `admin`.

## Related

- Ownership applied to: [matches.md](matches.md).
- Locale persistence rationale: repo-root `docs/ARCHITECTURE.md` (i18n section).
