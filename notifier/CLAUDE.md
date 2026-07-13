# Clutchlab notifier (Go)

Event subscriber — **no inbound HTTP, not behind nginx, no database**. It SUBSCRIBEs to
the Redis pub/sub events channel the worker publishes on and turns events into Discord
webhook messages. The worker doesn't know this service exists — that decoupling is the
whole lesson (docs/ARCHITECTURE.md, "Event-driven notifications").

- **How to build Go services here** (layout + conventions): [`../worker/docs/ENGINEERING.md`](../worker/docs/ENGINEERING.md)
- App-wide build & run: repo-root [`../docs/ENGINEERING.md`](../docs/ENGINEERING.md)

## Essentials

- Layout is `cmd/notifier/main.go` + `internal/{config,sub,discord}`.
- The channel is **Redis pub/sub** (`clutch_events`, `EVENTS_CHANNEL`) carrying JSON
  events `{ event, v, match_id, … }` — the contract lives in `worker/internal/events`
  and `notifier/internal/sub`; **change both sides in the same commit**.
- Delivery is **fire-and-forget**: events published while the notifier is down are gone.
  Accepted for notifications (a missed ping ≠ lost data); the earned upgrade is Redis
  Streams behind the same interfaces.
- `DISCORD_WEBHOOK_URL` empty → **log-only mode** (the pipeline is testable without
  Discord). The webhook URL is a secret — env only, never committed.
- This service is the only backend allowed to render human sentences: it is the
  "frontend" for the Discord channel, the same role React plays for the browser.
  Unknown event types are skipped silently — subscribers must tolerate new events.
- Toolchain pinned to **Go 1.24**; `air` pinned to match (v1.61.7).
