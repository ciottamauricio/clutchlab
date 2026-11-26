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
  their own numbers. The admin picks the player from the catalog of SteamIDs already seen in
  demos (`/admin/players`) rather than typing a raw id. Not every account has one (a
  coach/analyst never played). Distinct from `team_players`, which says a SteamID *plays for a
  team*, not that it *is an account*.
- **Token** — a Sanctum personal access token; the bearer credential for the API.

## Entities

### `users` (Laravel default + additions)

- `id`, `name`, `email` (unique), `password` (hashed), **`locale`** (default `en`), timestamps.
- `locale`: the user's language choice, persisted across devices (frontend i18n seam).
- **`role`** (default `member`): global role, cast to `App\Enums\UserRole` (`member` | `admin`).
  **Out of `Fillable`** — register/login can never set it; an admin assigns it deliberately.
- **`steam_id`** (nullable, unique, string): the account's own SteamID64. Also out of `Fillable`.
- **Profile fields** (all nullable, user-editable): `player_role` (in-game role: awper/rifler/igl/
  entry/lurker/support/coach), `bio`, and gear — `pc`, `mouse`, `keyboard`, `headset`, `monitor`,
  `mousepad`. A user edits these on their own profile; they're in `Fillable` (the self-edit path
  only fills these + `name`, never role/steam_id/email/password).
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
the caller's **visible** matches — and stats are aggregated from `kill_events` for the rostered ids.

- **Catalog** (`GET /players`): derived from `match_player_stats` over the caller's visible
  matches (`GameMatch::visibleTo` — own uploads + their teams'), grouped by `steam_id`. Returns
  `{ steam_id, name (latest), match_count (games) }`, most-played first, equal counts by Steam
  ID. **No separate players table** — the catalog is a read projection over match data.
  `PlayerStats` `PlayerList::seenBy`. The admin's pick list (`GET /admin/players`) is the same
  over **every** match, in name order as the database collates names (`PlayerList::all`).
- **Team stats** (`GET /teams/{team}/stats?from=&to=`): the roster's board, scoped to **the
  team's own matches** (those filed under it) and the roster's `steam_id` set, so every member
  sees the same board, independent of who's viewing. `PlayerStats` `TeamBoard`.
  - Optional `from`/`to` (`YYYY-MM-DD`) restrict it to matches **played** in that range (on
    `matches.played_at`, from `00:00:00` of `from` to `23:59:59` of `to`), so undated matches
    drop out once a bound is set. A value that isn't a date is ignored. The UI defaults `from`
    to the first of the current month.
  - Empty roster or no match in range → an empty board, not the roster with zeroes.
  - One row per rostered player, including those who never played (all zeroes). Name: the
    roster nickname, else the latest name, else the Steam ID.
  - Per player, by the counting rules below: `games`, `kills`, `deaths`, `kd`, `hs_pct`,
    `entry_kills`, `first_deaths`, `clutches`. Sorted by kills, then Steam ID.
- **Profile stats** (`GET /profile/stats`): the caller's linked Steam ID over **every match they
  can see**. `PlayerStats` `PlayerProfile`.
  - No linked Steam ID → `stats: null` and empty lists. No visible match → all zeroes.
  - `stats`: the board's line, without a name.
  - `recent`: the last 10 games the player is on the scoreboard of, by played time (upload time
    when undated, then the later match), shown oldest first; per game the scoreboard's kills
    and deaths, `kd`, and `won` (true or false from the player's side, null for a draw or an
    unknown score or side). Dates are printed as stored (`"2026-06-30 00:57:00"`).
  - `weapons`: the top 6 by kills, ties by weapon name.
  - `clutches`: the 8 latest clutches won, by played-else-upload time then round, with their
    kills. Here `tick_rate` and each kill's `hitgroups` are printed as Postgres stores them (a
    string, and jsonb's text such as `'{"head": 91}'`); every other answer gives a number and an
    object. Kept as it was.

### How a player's numbers are counted

Written once, in `app/PlayerStats/` (spec 004): the domain defines each rule in PHP, and the
Postgres adapter counts the same thing in SQL, held equal by a contract test. The board, the
profile, the [awards](awards.md) and the [match page](matches.md) all use these definitions.
The worker sets the flags they read (`opening`, `clutch`, `hitgroups`; see
[matches.md](matches.md)).

- **Kills** are the `kill_events` rows with the player as killer; **deaths**, as victim;
  **headshots**, kills flagged `headshot`.
- **Opening kills** (`entry_kills`) are kills flagged `opening`; **opening deaths**
  (`first_deaths`, the first-blood award) are deaths flagged `opening`. The worker flags every
  kill at a round's earliest tick, so a round can have several.
- **Clutches won** count rounds, not kills: distinct `(match, round)` pairs with a kill flagged
  `clutch > 0`. The worker keeps that flag only when the clutcher's side won the round.
- **Games** are the matches the player is on the scoreboard (`match_player_stats`) of.
- **K/D** is kills per death to two places, or the kills with no deaths; **HS%** is headshots
  over kills as a whole percentage, 0 with no kills.
- **The latest name** is the one from the most recently uploaded match in scope; on the same
  upload second, the higher match id.
- **Ties**: a ranked list breaks equal values by Steam ID, ascending (spec 004 Q6; before, the
  order among equals was whatever Postgres returned).

What the data's corner cases count as, known and kept (spec 004 Q7):
- A **self-kill** (by `world`, or a player's own grenade) counts as a kill *and* a death for the
  same player, and as an opening kill and an opening death when it opens the round.
- A **team kill** counts as a kill for the killer.
- A kill with **no killer** (the bomb) counts under an empty Steam ID, like a player.
- Analytics live in Postgres; Meilisearch stays the text-search read model only.
- **Player clutches** (`GET /players/{steamId}/clutches`): every won 1vN the player made across
  the caller's visible matches, grouped by `(match, round)` — the same shape as
  [matches.md](matches.md)'s `clutches`, but each card carries its own `map`/`demo`/`tick_rate`
  so the "watch in game" jump works across matches. By match, then round. Powers the clickable
  clutch cell on the stat board. `PlayerStats` `PlayerClutches`.
  - Known and kept (spec 004 Q7): the list spans **every visible match, all time**, while the
    cell it opens from counts the team's matches in the board's date range, so the dialog can
    list more clutches than the cell shows.
- Stats need `kill_events.victim_steam_id` (present since the Step 4 search read model) so
  death-side metrics key on the victim's stable id, not the name.

### `permissions`, `global_role_permissions`, `team_role_permissions`

- **`permissions`** — the ability catalog: `key` (unique, e.g. `match.delete`), `scope`
  (`app`|`team`), `label`, `description`. Kept in sync with `PermissionCatalog::abilities()`.
- **`global_role_permissions`** — app-scope grants: `role` (global role) × `permission_id`,
  `unique(role, permission_id)`.
- **`team_role_permissions`** — team-scope grants: `role` (team role) × `permission_id`,
  `unique(role, permission_id)`.

### `matches` (addition)

- `user_id` (fk `users`, nullable, indexed) — the uploader. Legacy pre-auth rows are null.
- `team_id` (fk `teams`, nullable, indexed, `nullOnDelete`) — the team the match is shared
  with; null = private to the uploader. Deleting a team un-shares its matches (sets null),
  it doesn't delete them.

## Roles & permissions

**Permissions are data, not hard-coded.** Each *ability* (e.g. `match.delete`, `awards.view`) is
granted to a *role*, and a master admin edits the grant matrix at runtime. There are two scopes,
matching the two role axes:

- **Team-scope** abilities are granted to a **team role** (`owner`/`igl`/`player`/`coach`) and
  resolved against the relevant team — a match's team, or a team directly. A user "has" a
  team-scope ability if their role *in that team* is granted it.
- **App-scope** abilities are granted to a **global role** (`member`/`admin`) and gate whole
  pages, independent of any team.

The **catalog** of abilities and the **default grants** (which reproduce the original
hard-coded rules exactly) live in `App\Authorization\PermissionCatalog`; the seeder writes them
to the DB on first run and never clobbers later admin edits. Current abilities:

| Ability | Scope | Meaning |
|---|---|---|
| `match.view` | team | See a team's matches |
| `match.delete` | team | Delete a team match |
| `match.reparse` | team | Re-parse a team match |
| `team.upload_match` | team | Upload a demo to the team |
| `team.manage_members` | team | Add/remove members, change team roles |
| `team.manage_roster` | team | Edit the in-game roster (SteamIDs) |
| `team.update` | team | Rename / delete the team |
| `training.manage` | team | Schedule / edit / cancel trainings |
| `tactics.create` | team | Create a tactic shared with the team |
| `tactics.edit` | team | Edit the team's shared tactic boards |
| `tactics.delete` | team | Delete the team's shared tactics |
| `awards.view` | app | Open the awards page |
| `search.use` | app | Use kill search |
| `tactics.view` | app | Open the tactics page (per-tactic access still applies) |

**Default grants** (seeded; editable): owner → all team abilities; igl → matches + trainings +
tactics create/edit/delete; coach → match.view + trainings + tactics create/edit/delete;
player → match.view + tactics create/edit. member → all three app pages; admin → (needs none,
bypasses).

Two rules stay outside the grant tables, on purpose:
- **The match uploader** always keeps `view`/`delete`/`reparse` over their own upload, even with
  no team — a carve-out in the service, not a grant.
- **Viewing a team you belong to** is plain membership, not a grantable ability.

The global **`admin`** is orthogonal: it overrides every check via a single `Gate::before`
short-circuit (returns `true` for admins, `null` otherwise so non-admins fall through) — so it
needs no grant rows and can never lock itself out by editing the matrix.

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
9. **Global role & SteamID are admin-only and not mass-assignable.** Only `admin` users reach
   `/admin/users`; `role`/`steam_id` are set on the model explicitly (never via register/login).
10. **The last admin can't be demoted** — an attempt returns `admin.last_admin` (422), so the
    platform always has at least one admin.
11. **An admin can't delete their own account** (`admin.cannot_delete_self`, 422) — which also
    means the last admin can never delete the platform's admin access away. Deleting a user
    keeps their uploaded matches (owner set null); memberships and tokens are removed.

## Validation (boundary)

- **Register:** `name` required; `email` required/email/unique; `password` required/confirmed/min:8.
- **Login:** `email` required/email; `password` required.
- **Create team:** `name` required.
- **Add member:** `email` required/exists; `role` required/in `owner,igl,player,coach`.
- **Add roster player:** `steam_id` required/string; `nickname` nullable/string. Re-adding an
  existing `steam_id` is idempotent (updates the nickname), so there's no "already rostered" error.

¹ The stat board is scoped to **the team's own matches** (those filed under it): it shows how the
rostered ids performed in the team's games, and every member sees the same numbers.

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
| GET | `/api/me` | current user (incl. profile fields, role, linked SteamID, app-scope `abilities`) |
| PATCH | `/api/profile` | edit own profile (name + player_role/bio/gear) |
| GET | `/api/profile/stats` | own profile analytics across visible matches — aggregate stats (null if no SteamID linked), recent form (K/D + W/L per match), top weapons |
| PATCH | `/api/profile/password` | self-service password change (current password required); revokes every other session, keeps this one |
| GET/POST/GET | `/api/matches…` | uploader + team-shared (see [matches.md](matches.md)) |
| GET | `/api/players` | catalog of players seen across my matches |
| GET | `/api/players/{steamId}/clutches` | a player's won clutches across my matches |
| GET | `/api/teams` | teams I belong to |
| POST | `/api/teams` | create a team (I become `owner`) |
| GET | `/api/teams/{team}` | team + members + roster (member only) |
| GET | `/api/teams/{team}/stats` | roster stat board, optional `from`/`to` date range (member only) |
| POST | `/api/teams/{team}/members` | add a member by email + role (owner only) |
| DELETE | `/api/teams/{team}/members/{user}` | remove a member (owner only) |
| POST | `/api/teams/{team}/players` | add a player to the roster by steam_id (owner only) |
| DELETE | `/api/teams/{team}/players/{steamId}` | remove a rostered player (owner only) |

**Admin (`auth:sanctum` + `can:admin`)**

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/admin/users` | list every user (role, linked SteamID, teams) |
| PATCH | `/api/admin/users/{user}` | set a user's global `role` and/or `steam_id` |
| DELETE | `/api/admin/users/{user}` | delete a user (not yourself); memberships/tokens go, uploaded matches survive ownerless |
| GET | `/api/admin/players` | catalog of players (SteamID64 + latest name + match count) seen across all matches, by name — the pick list for linking |
| GET | `/api/admin/permissions` | the grant matrix: ability catalog, roles per scope, current grants |
| PUT | `/api/admin/permissions` | replace one `(scope, role)`'s grants (`{ scope, role, keys[] }`) |

## Authorization (policies)

`App\Contracts\PermissionService` (bound to `DbPermissionService`, a **singleton** so its
grant-table cache is shared across a request) is the single source of truth. It reads the live
grant tables — nothing is hard-coded.

- `Gate::before` (in `AppServiceProvider::boot`): a global `admin` passes every check.
- **App-scope gates** are registered from the catalog in `boot()` — every app ability becomes a
  named gate (`->middleware('can:awards.view')` etc.) resolved by `PermissionService::canApp`.
- `Gate::define('admin')`: the named ability behind admin-only routes. Admins short-circuit via
  `Gate::before`; non-admins fall here and are denied (**403**).
- `GameMatchPolicy`: `view`/`delete`/`reparse` all delegate to `canOnMatch($user, $key, $match)`
  — the uploader carve-out OR the user's team-role grant in `match.team_id`.
- `TeamPolicy`: `view` → plain membership; `manageMembers`/`manageRoster`/`update`/`delete`/
  `uploadMatch` → `canOnTeam($user, $key, $team)` against the user's role in that team.
- **The client** learns app-scope abilities from `/me` (`abilities: [...]`, full set for admins)
  and per-match team-scope abilities from each match's `can: { delete, reparse }` (resolved
  through the policy). The UI hides what it can't use; the server still enforces every request.
- **Editing the matrix**: admin-only `GET/PUT /admin/permissions` — `PUT` replaces one
  `(scope, role)`'s grants with the posted `keys` (`UpdateRolePermissionsAction`, transactional).

## Error codes

- `auth.invalid_credentials` — bad email/password (login).
- `auth.current_password_incorrect` — wrong current password on a self-service password change.
- `match.invalid_team` — upload `team_id` isn't a team the caller may upload to (owner/igl).
- `user.invalid_role` — admin set a global role other than `member`/`admin`.
- `user.invalid_steam_id` — linked SteamID isn't a 17-digit SteamID64.
- `user.steam_id_taken` — that SteamID is already linked to another account.
- `admin.last_admin` — refused: demoting this user would leave no admin.
- `admin.cannot_delete_self` — an admin tried to delete their own account.
- `permission.invalid_scope` / `permission.invalid_role` / `permission.unknown_ability` — a grant
  edit named a scope, role, or ability key that doesn't exist (or is out of scope).
- `team.already_member` — the user is already in the team.
- `team.steam_id_required` — no player selected when adding to the roster.
- 401 unauthenticated · 403 authorization · 422 validation (field codes).

## Known limitations / later

- **Permissions are per-role, not per-user** — an admin edits what a *role* can do; there's no
  grant to an individual user, and no custom roles beyond the fixed four team / two global ones.
- App-scope abilities cover whole pages (awards/search); tactics splits page-open
  (`tactics.view`, app-scope) from the writes (`tactics.create/edit/delete`, team-scope
  resolved against the tactic's team — a private draft is the creator's alone).
- No invitation/acceptance flow — an owner adds existing users directly by email.
- A match belongs to at most one team; there's no re-assigning a match's team after upload yet.
- Removing the last `owner` of a team isn't prevented.
- **SteamID linking is admin-assigned only** — no self-service "Sign in through Steam" flow yet.
- The admin panel manages the global role and SteamID; it doesn't edit team membership (that's
  the team owner's job) — it only shows each user's teams read-only.
- **Password reset is self-service only** (you must know your current password). A "forgot
  password" email flow is a later step.

## Related

- Ownership applied to: [matches.md](matches.md).
- Locale persistence rationale: repo-root `docs/ARCHITECTURE.md` (i18n section).
