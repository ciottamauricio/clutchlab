# Notifications domain

## Status

**Implemented** (Step 5). Facts are published on the `clutch_events` Redis pub/sub
channel; independent subscribers react. New reactions are additive.

## Purpose

Event-driven notifications, decoupled by publish/subscribe. A producer publishes a
**fact** ("this happened") on `clutch_events`; each subscriber decides on its own whether
that fact deserves a notification and how to send it. The publisher never names its
subscribers — that decoupling is the whole point (and the study subject). Facts, not
commands: see `docs/ARCHITECTURE.md` "Commands vs. events".

## The channel and its participants

`clutch_events` — Redis pub/sub, JSON `{ "event", "v", ...fields }`, additive-only.

**Publishers**
- **worker** (Go) — parse outcomes: `match.parsed`, `match.failed`.
- **api** (Laravel) — `training.scheduled`, via the `EventBus` contract.

**Subscribers** (one fact fans out to all of them)
- **notifier** (Go) — posts to a Discord webhook; log-only when `DISCORD_WEBHOOK_URL`
  is unset.
- **events-listener** (Laravel) — the api image running `php artisan events:listen` as
  its own long-lived container. Turns facts into Laravel-side reactions that need the
  relational data or the mail stack. First reaction: **email a training's roster** on
  `training.scheduled`.

## Laravel as a subscriber (the mirror of `EventBus`)

- `App\Contracts\EventSubscriber` + `App\Queue\RedisEventSubscriber` — the consume side,
  the twin of `EventBus`/`RedisEventBus`. Blocking `SUBSCRIBE`; malformed or unknown
  messages are logged and skipped, never thrown, so one bad message can't kill the loop.
- `App\Console\Commands\ListenForEvents` (`events:listen`) — the daemon. It routes each
  decoded event to every `App\Events\Subscribers\EventHandler` whose `handles()` matches.
- Adding a reaction is **one class** implementing `EventHandler`, tagged in
  `AppServiceProvider` — never a `switch` to edit.
- The listener is **not the request path**, so handlers run **inline** (no queue worker
  needed): the reason to move email off the request — not blocking scheduling — is already
  satisfied by the daemon being off the request path.

### `EmailTrainingRoster` (first handler)

Reacts to `training.scheduled`. The event payload carries only a player **count** (a fact,
not a data dump), so the handler **re-reads the roster by `training_id`** and sends one
`TrainingScheduledMail` per player, skipping any without an email. A training deleted
before delivery is a logged no-op.

## Delivery guarantees

- Pub/sub is **at-most-once**: a fact published while a subscriber is down is missed by
  that subscriber (documented tradeoff, same as the notifier). Tolerable for "you missed a
  ping"; if a *guaranteed* email is ever needed, the earned upgrade is Redis Streams so a
  restarted subscriber replays what it missed.
- Mail transport is `MAIL_MAILER` — **`log` by default** (rendered email lands in
  `storage/logs`, visible in Loki/Grafana) so the pipeline works with zero credentials.
  Swap to `smtp` via env only (secrets never committed).

## Related

- The publishing side of trainings: [trainings.md](trainings.md).
- First worker-published event source: parse completion in [matches.md](matches.md).
- Why the notifier is Go but this is Laravel, and why this is pub/sub not a queue:
  `docs/ARCHITECTURE.md`.
