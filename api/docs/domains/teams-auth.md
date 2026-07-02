# Teams & Auth domain

## Status

**Planned — Step 2.** Not yet implemented. This is a placeholder; fill it in using the
template in [README.md](README.md) when the domain is built, and update the index.

## Purpose

Real authentication plus users and teams with roles (owner / IGL / player / coach),
and the authorization/ownership layer the app deliberately dodges at first — starting
with scoping the Matches domain to an owner.

## Will own (expected)

- `users` (plus a `locale` column so an i18n language choice persists across devices).
- `teams`, team memberships, and roles/permissions.
- Authentication (likely Sanctum) and authorization policies.

## Rules to define when built

- **Ownership model:** does a match belong to a user or a team? Reads/writes scoped to
  owned resources; ownership violations return **403**, not 404.
- **Roles & permissions matrix:** who can upload demos, view a team's matches, manage
  members, etc.
- **Rate-limit auth endpoints** (login/register/password).
- Team invitation / join flow.

## Open questions

- Match ownership: user-owned vs team-owned (or both, with sharing)?
- How roles map to concrete permissions across domains.

## Related

- Roadmap: repo-root `docs/ROADMAP.md` ("Teams + auth/roles").
- First domain to be retrofitted with ownership: [matches.md](matches.md).
