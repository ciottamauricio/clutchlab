# Domain documentation

AI-consumable business rules for each domain in the Clutchlab API. The point is
that when a feature is built, the rules, invariants, lifecycle, and edge cases are
written down **here first** — code follows the doc, and the doc is updated in the
same change when rules change.

## Index

| Domain | Status | Doc |
|--------|--------|-----|
| Matches — demo upload + parsing | Implemented (Step 1) | [matches.md](matches.md) |
| Teams & Auth — roles, ownership | Planned (Step 2) | [teams-auth.md](teams-auth.md) |
| Tactics board | Implemented (Step 3) | [tactics.md](tactics.md) |
| Search | Implemented (Step 4) | [search.md](search.md) |
| Kill heatmap — kill positions on the radar | Implemented | [heatmap.md](heatmap.md) |
| Notifications | Planned (Step 5) | [notifications.md](notifications.md) |
| DORA — delivery metrics | Instrumented; awaits a deploy pipeline | [dora.md](dora.md) |

## What "business rules" means here

Not code walkthroughs. Capture what is *not* obvious from reading a controller:

- **Invariants** — things that must always hold ("one upload ⇒ exactly one match").
- **Lifecycle / state machine** — the valid states and transitions.
- **Authorization** — who may do what.
- **Error semantics** — what each returned code means.
- **Cross-service contracts** — payloads/APIs shared with the worker or other services.

If a rule can be phrased as "the system must (never) …", it belongs here. If it's
just "here's how this method works", it belongs in code comments, not here.

## Template for a new domain doc

```markdown
# <Domain> domain

## Purpose
One or two sentences: what this domain is responsible for.

## Status
Implemented (Step N) | Planned (Step N). Note what is / isn't built yet.

## Ubiquitous language
Key terms and exactly what they mean in this domain.

## Entities
Tables/models, their fields, who writes each field, and non-obvious notes.

## Lifecycle
The state machine (if any) and the rules about who may cause each transition.

## Business rules & invariants
Numbered, testable statements. Each should map to code and/or a test.

## Validation (boundary)
What is validated at the HTTP edge, and the error codes emitted.

## API surface
Endpoints, methods, response shape, auth/throttling.

## Error codes
Every code this domain can return and what it means.

## Authorization & ownership
Who owns what; how violations are handled (403).

## Known limitations
Deliberate gaps and why, so they aren't "fixed" by accident.

## Related
Links to architecture docs and the other services involved.
```
