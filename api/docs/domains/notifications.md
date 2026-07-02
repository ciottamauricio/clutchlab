# Notifications domain

## Status

**Planned — Step 5.** Not yet implemented. Placeholder; fill in using the template in
[README.md](README.md) when built.

## Purpose

Event-driven notifications (e.g. a Discord bot, since CS teams live in Discord). A
domain event such as `MatchParsed` is **published** by the producer; a separate notifier
service **reacts** to it. The publisher does not know its subscribers — the point of the
step is publish/subscribe decoupling.

## Will own (expected)

- The set of published domain events and their payloads.
- User/team notification preferences (channels, opt-in/out).

## Rules to define when built

- **Which domain events are published** (e.g. `MatchParsed`) and their exact payloads —
  this is a contract other services depend on.
- Delivery guarantees (at-least-once? de-duplication on the subscriber side?).
- Preference model: who gets notified about what, and where.

## Related

- Roadmap: repo-root `docs/ROADMAP.md` ("Notifications + Discord bot → event-driven pub-sub").
- Likely first event source: parse completion in [matches.md](matches.md).
