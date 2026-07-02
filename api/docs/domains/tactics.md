# Tactics board domain

## Status

**Planned — Step 3.** Not yet implemented. Placeholder; fill in using the template in
[README.md](README.md) when built.

## Purpose

A collaborative tactics board. The simple version is CRUD (a `tactics` record holding a
JSON board + metadata) and lives here in Laravel. The *valuable* version — real-time
collaborative editing, many people moving pieces at once — is a **separate Go service
(`realtime`)**, because that workload (stateful, concurrent, connection-based) is
fundamentally different from the batch parser.

## Will own (expected, Laravel side)

- `tactics` — persistent board state (JSON), title, ownership/sharing.
- The cross-domain link from a tactic to a real parsed round in the Matches data
  ("here's the round we tried this execute").

The live-editing transport, rooms, and broadcast belong to the `realtime` service, not
this API.

## Rules to define when built

- Tactic ownership and sharing (ties into Teams & Auth).
- The contract between Laravel (persistence) and `realtime` (live editing): who is the
  source of truth, and when state is saved back.
- Referencing a parsed round across the service/data boundary.

## Related

- Roadmap: repo-root `docs/ROADMAP.md` ("Real-time collaborative tactics board").
- Depends on: [teams-auth.md](teams-auth.md), and round data from [matches.md](matches.md).
