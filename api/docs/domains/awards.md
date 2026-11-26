# Awards domain

## Status

Implemented. Superlative leaderboards over parsed matches.

## Purpose

"Awards" are for-fun, cross-match superlatives — one card per metric, ranking players against
each other. Unlike [search](search.md) (which lists matching kills) and the team
[stat board](teams-auth.md) (a full table for one roster), each award answers a single
question — "who dies first the most?", "who's the biggest one-trick?" — with a top-5.

## Scope & source

- **Analytics live in Postgres**, not Meilisearch: the counts come from `kill_events` (Meili
  stays the text-search read model). Same rationale as team stats. `PlayerStats` `Awards` and
  `AwardKills`, counting by the rules in [teams-auth.md](teams-auth.md#how-a-players-numbers-are-counted).
- **Visible-match-scoped.** Counts every match the caller can see — their own uploads plus
  their teams' matches (`GameMatch::visibleTo`) — not just what they uploaded.
- **Optional narrowing.** `team_id` (one of the caller's roster teams → its steam_ids) and
  `map` (on `kill_events.map`) restrict the field. With no team, *every* player seen across the
  caller's visible matches is ranked; with a team, only its roster, and a team with no roster
  ranks nobody. A `team_id` the caller isn't a member of counts as no team.
- **Names** are each player's latest name over all the visible matches, whatever the map.

## The awards

| Key | Metric | Side | Notes |
|---|---|---|---|
| `legshots` | Kills where the **legs** took the most damage | killer | the legs took damage, and at least as much as each other zone (`HitGroups::legsDominant`; a tie with another zone counts) |
| `first_blood` | Times the player was the **round's first death** | victim | the player's opening deaths |
| `headshot` | Highest **headshot rate** | killer | headshots over kills; min 20 kills so 1/1 can't win; a rate of 0 isn't listed |
| `eco` | Most kills with a **pistol or knife** | killer | `Weapons::ECO`: the frontend registry's Pistols values (`deserteagle`, not `deagle`) plus `knife` |
| `clutches` | Most **1vN rounds won** | killer | distinct `(match, round)` where `clutch > 0` |
| `one_trick` | Highest **single-weapon share** of kills | killer | the top weapon's kills over the kills with a weapon; min 20 such kills; the top weapon is the most kills, ties by weapon name |

Each award lists its top 5 with a value above 0, equal values by Steam ID (spec 004 Q6). An
award with no qualifiers in scope (e.g. no clutches yet) is omitted from the response.

Known and kept (spec 004 Q7):
- The kills with **no killer** (the bomb, the world) count under an empty Steam ID. With no team
  filter that "player" can pass the 20-kill floor: on the dev data it leads one-trick ("95%",
  `c4`, no name). Its row can't be drilled into: an empty `steam_id` lists no kills.
- `label` and `hint` are English sentences the page shows as they come, against
  codes-not-sentences; they live in the HTTP layer (`AwardResource`) for the follow-up.

## API surface (Laravel, `auth:sanctum`)

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/awards` | leaderboards across my matches |
| GET | `/api/awards/kills` | the kills behind one award for one player |

`/awards` query params: `team_id` (a roster team of mine), `map`. Response: `{ data: { awards:
[ { key, emoji, label, hint, leaders: [ { steam_id, name, value, sub } ] } ] } }`. `value`/`sub`
are pre-formatted display strings; `sub` on `one_trick` is the raw weapon value (the frontend
maps it to a label).

`/awards/kills` query params: `key` (one of the award keys), `steam_id`, `map`. Returns the
matching kills — `{ data: { kills: [ { match_id, map, demo, tick_rate, round, killer_name,
victim_name, weapon, side, headshot, tick, hitgroups } ] } }` — each carrying its own
demo/tick so the frontend's "watch in game" jump works. Offence awards key on the killer;
`first_blood` keys on the victim (that player's round-opening deaths). One-trick lists the kills
with the weapon the award named, picked by the same rule. Ordered by match, then tick. An unknown
`key` or an empty `steam_id` gives no kills. Powers the click-through dialog on a leaderboard row.

## Related

- Roster teams & the `team_id` scoping: [teams-auth.md](teams-auth.md).
- The kill-list counterpart (and the shared roster team filter): [search.md](search.md).
- Metrics reuse fields added for the heatmap/clutch work: [heatmap.md](heatmap.md).
