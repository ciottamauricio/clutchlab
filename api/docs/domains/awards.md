# Awards domain

## Status

Implemented. Superlative leaderboards over parsed matches.

## Purpose

"Awards" are for-fun, cross-match superlatives — one card per metric, ranking players against
each other. Unlike [search](search.md) (which lists matching kills) and the team
[stat board](teams-auth.md) (a full table for one roster), each award answers a single
question — "who dies first the most?", "who's the biggest one-trick?" — with a top-5.

## Scope & source

- **Analytics live in Postgres**, not Meilisearch: awards are `GROUP BY` aggregations over
  `kill_events` (Meili stays the text-search read model). Same rationale as team stats.
- **Owner-scoped.** Only the caller's matches (`kill_events.owner_id`) are counted.
- **Optional narrowing.** `team_id` (one of the caller's roster teams → its steam_ids) and
  `map` restrict the field. With no team, *every* player seen across the caller's matches is
  ranked; with a team, only its roster.

## The awards

| Key | Metric | Side | Notes |
|---|---|---|---|
| `legshots` | Kills where **legs** took the most damage | killer | argmax over the hitgroup jsonb |
| `first_blood` | Times the player was the **round's first death** | victim | counts `opening` kills' victims |
| `headshot` | Highest **headshot rate** | killer | ratio; min 20 kills so 1/1 can't win |
| `eco` | Most kills with a **pistol or knife** | killer | eco weapon set mirrors the frontend registry |
| `clutches` | Most **1vN rounds won** | killer | distinct `(match, round)` where `clutch > 0` |
| `one_trick` | Highest **single-weapon share** of kills | killer | ratio; min 20 kills |

An award with no qualifiers in scope (e.g. no clutches yet) is omitted from the response.

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
`first_blood` keys on the victim (that player's round-opening deaths). Powers the click-through
dialog on a leaderboard row.

## Related

- Roster teams & the `team_id` scoping: [teams-auth.md](teams-auth.md).
- The kill-list counterpart (and the shared roster team filter): [search.md](search.md).
- Metrics reuse fields added for the heatmap/clutch work: [heatmap.md](heatmap.md).
