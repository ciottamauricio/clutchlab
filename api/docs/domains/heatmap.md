# Kill heatmap domain

## Purpose
Plot where kills happened on a match's radar map — a positional read over the same kill
events that already back search, so you can see e.g. "where did the AWP kills land".

## Status
Implemented. Reuses the `kill_events` source of truth (see [search.md](search.md)); adds two
world-coordinate columns and one read endpoint. No new store — positions live on kill events.

## Ubiquitous language
- **World coordinates** — the raw in-game position (Source units) of the victim at the moment
  of death, `victim_x` / `victim_y`. Map-agnostic facts; the worker records them verbatim.
- **Radar space** — the 0..1 normalized position on a map's radar overview image. This is a
  *render concern*, derived from world coords by a per-map calibration. It lives in the
  **frontend**, next to the radar PNGs it applies to — not in the database.

## Entities
`kill_events` (already defined in search.md) gains:

| Column | Type | Written by | Notes |
|---|---|---|---|
| victim_x | double, nullable | worker | victim world X at death (`Player.Position()`) |
| victim_y | double, nullable | worker | victim world Y at death |
| hitgroups | jsonb, nullable | worker | body-zone damage of the killing engagement (see below) |

Nullable because kills parsed before this feature (or on a demo where a position is
unavailable) have no coordinates; the heatmap simply skips those points.

### Hitgroups (body hitgroup map)
`hitgroups` is a JSON object of body zone → total health damage the **killer dealt the
victim in that round**, aggregated by the worker from `events.PlayerHurt`. Zones collapse
demoinfocs hit groups into five: `head` (head+neck), `chest`, `stomach`, `arms` (left+right),
`legs` (left+right); Generic/Gear hits are dropped. Example: `{"head":140,"chest":27}`.
Damage can exceed 100 (over-damage across multiple hits). Null for kills with no tracked hits
(knife, bomb, fall). The frontend renders this over a body silhouette
(`frontend/public/hitgroups/body.png`, a CS2-agnostic public-domain image used as a themeable
mask), one damage badge per hit zone — shown in a dialog from the heatmap and as an expandable
row under a search result.

## Business rules & invariants
1. Coordinates are the **victim's** position (where someone died), not the killer's.
2. The worker stores **world** coordinates only. It never bakes in radar/pixel math — the
   calibration belongs to whoever owns the radar image (the frontend).
3. Warmup kills are excluded upstream (same as all kill events).
4. A point with no coordinates (`victim_x`/`victim_y` null) is omitted from the heatmap, never
   rendered at 0,0.

## API surface (Laravel, `auth:sanctum`)

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/matches/{match}/kill-positions` | all kill positions for one match |

Owner-scoped by the match `view` policy (403 on someone else's match). Returns every kill
with coordinates for the match in one payload (a match is ~150 kills — small enough that the
frontend fetches once and filters client-side by player/weapon/side/headshot). Response:

```json
{ "data": {
  "map": "de_inferno",
  "points": [
    { "round": 3, "killer_name": "...", "victim_name": "...",
      "weapon": "ak47", "side": "T", "team": "T", "headshot": true,
      "hitgroups": { "head": 140, "chest": 27 }, "tick": 8866, "x": 145.2, "y": -320.8 }
  ],
  "tick_rate": 64,
  "demo": "original-name.dem"
} }
```

### Watch in game
Each kill carries its demo `tick`; the response also includes the match `tick_rate` and the
demo filename. The UI turns these into a copy-paste console command that jumps CS2's demo
playback to ~5 s before the kill:
`playdemo "<demo>"; demo_gototick <tick − 5·tick_rate>`. The demo itself is fetched via the
owner-scoped download endpoint below (a browser can't push commands into a running game, so
this is copy-paste, not one-click). `tick`/`tick_rate` are null on matches parsed before this
feature — reparse to backfill.

`side` is the killer's side at the moment of the kill; `team` is their whole-match team
(stable across the half-time swap — see [search.md](search.md)), which the heatmap's team
filter uses. The frontend filters this set client-side by player/weapon/team/headshot.

## Authorization & ownership
Same as matches: only the match owner may read its positions; violations return 403 via
`GameMatchPolicy@view`.

## Known limitations
- **Single-level maps only.** Multi-level maps (Nuke, Vertigo, Train) have upper/lower radar
  images; we use one overview, so lower-level kills land on the wrong layer. The `_lower`
  radar variants exist in the asset set but layer selection isn't implemented.
- **Calibration is per radar-image version.** The frontend calibration constants match the CS2
  radar PNGs shipped in `frontend/public/radars/`. If those images are replaced with a
  reworked map, the constants must move with them.
- **Kill positions, not full trajectories.** Only the death location is stored, not the
  killer's position or the pre-death path.

## Related
- Source of truth and event shape: [search.md](search.md).
- Frontend radar assets + calibration: `frontend/src/features/matches/radar.js`.
- Worker parser: `worker/internal/parser/parser.go`.
