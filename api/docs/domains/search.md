# Search domain

## Status

**Planned — Step 4.** Not yet implemented. Placeholder; fill in using the template in
[README.md](README.md) when built.

## Purpose

Rich queries over parsed events — "every round where we lost a 5-on-3", "all my AWP
opening kills on Mirage". Backed by a dedicated search engine (Meilisearch or
Elasticsearch) holding a **second read model** fed from parser output. Laravel exposes
the query API and owns keeping the index in sync.

## Will own (expected)

- The indexing pipeline from parsed match/event data into the search engine.
- The query endpoints and result shaping.

## Rules to define when built

- **What gets indexed** (which events/fields) and the document shape.
- **Sync & consistency contract:** this is CQRS with eventual consistency — define how
  and when the index is updated after a parse, and what staleness is acceptable.
- Result scoping to the requesting user/team (ties into Teams & Auth).

## Related

- Roadmap: repo-root `docs/ROADMAP.md` ("Search → dedicated search service").
- Source data: [matches.md](matches.md). Scoping: [teams-auth.md](teams-auth.md).
