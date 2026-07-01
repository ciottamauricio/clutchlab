# Architecture & Design Decisions

This document captures **why** the system is shaped the way it is. The features matter less
than the boundaries between services — that's the thing being studied.

## Guiding principle: find the seam

The most common mistake in a learning-microservices project is splitting things that should
be one service, then drowning in plumbing. A service boundary must be *earned*. The question
to ask of every candidate service:

> Does this workload differ from the rest in a way that a language or runtime boundary
> actually addresses?

- CPU-bound parsing, high-concurrency websockets, heavy scheduled aggregation → maybe a service.
- "It's a different noun in my domain" (users vs comments vs matches) → almost never; that's
  just a module inside one service.

## Topology

```
                    host:8080
                        │
                   ┌────▼─────┐
                   │  nginx   │  (reverse proxy / gateway)
                   └──┬────┬──┘
              /       │    │      /api/*
        ┌─────────────▼┐  ┌▼──────────────┐
        │   frontend   │  │      api       │  Laravel (CRUD)
        │  React/Vite  │  │                │
        └──────────────┘  └───┬────────┬───┘
                         rpush │        │ pdo
                         ┌─────▼──┐  ┌──▼───────┐
                         │ redis  │  │ postgres │
                         └────▲───┘  └──▲───────┘
                        blpop │         │
                        ┌─────┴─────────┴──┐
                        │      worker      │  Go (demo parser)
                        └────────┬─────────┘
                                 │ get/put demo files
                            ┌────▼────┐
                            │  minio  │
                            └─────────┘
```

The `worker` has **no inbound HTTP and is not behind nginx**. It's a background consumer.
The queue is the only thing connecting the web world and the compute world — that's the
physical shape of the async boundary.

## Why each boundary earns its place

### Go worker vs Laravel (the core split)

Demo parsing is CPU-bound and slow; the web app around it is ordinary CRUD. Two very
different workloads = the reason the boundary exists.

- **Go** has `demoinfocs-golang` (`github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs`),
  the mature Source 2 (CS2) parser, built for performance and concurrency. Requires Go 1.24+.
- **PHP could** parse a demo, but slowly, single-threaded, with no mature library.
- So Go exists *only* because of the parsing workload. That's a real justification, not a
  contrived one.

### Sync vs async

Parsing takes seconds-to-minutes. A synchronous Laravel→Go call would hang and time out.
This forces async, queue-based communication — and with it the real costs of async:
- the client gets no immediate answer (need a `status` field / state machine)
- worker crash mid-parse must be handled (retries, dead-letter queue)
- idempotency (what if a message is delivered twice?)

The CS2 use case makes you *feel* these instead of reading about them.

### Cross-language queue: the polyglot tax

The queue is a **plain Redis list with JSON**, not Laravel's native queue format. Laravel's
`queue:work` serializes PHP job objects Go can't read. So the price of two languages sharing
a queue is: you give up Laravel's queue conveniences on that channel and hand-roll the
contract — `rpush` a JSON blob on one side, `BLPOP` + unmarshal on the other. This is a real,
visible tradeoff of the polyglot boundary. Keep it visible, don't hide it.

Queue contract (keep both sides in sync — same commit when it changes):

```json
{ "match_id": 123, "demo_key": "demos/abc.dem" }
```

### Data ownership (a deliberate later refactor)

Start with a **shared database** between api and worker — simpler, lets you learn the queue
first. Then *deliberately* refactor to separate databases so you feel what breaks:
- how does the frontend show "my match + its stats" when they live in two services?
- API composition (Laravel calls Go's read API and stitches) vs denormalization (duplicate a
  summary into Laravel's DB, eventual consistency)?

There's no free answer — that *is* the lesson. Feeling the pain of the shared-DB version
first is more educational than starting "correct."

## The learning progression (the actual curriculum)

monolith-ish → split under justified pressure → feel the tradeoffs → decide what was worth it.

Do NOT start "fully microservices" on day one. Start with the shared DB + Redis queue (the
simplest thing that works), get the end-to-end async flow running, THEN refactor under
pressure you can actually feel.

## Repo strategy: monorepo

One repo. Each service keeps its **own** dependency manifest (`composer.json`,
`package.json`, `go.mod`) so services aren't entangled — a monorepo in the "one git repo"
sense, not a shared-tooling swamp.

Why: the compose file references `./api`, `./frontend`, `./worker` as siblings; atomic
cross-service commits (change the queue JSON on both sides in one commit); one clone + one
`up`. Splitting into multiple repos only wins under organizational/scale pressure (separate
teams, independent deploy cadence, huge tree) — none of which apply to a solo learning project.

Middle path for later: give each service its own CI pipeline *within* the monorepo, triggered
by path filters, for independent build/test without losing atomic commits.

## Internationalization (pt-BR + en)

Decision made up front because retrofitting it is painful; implementation deferred until
there's UI.

**The language boundary lives in the frontend. Backend services stay language-agnostic.**

- `api` (Laravel) and `worker` (Go) return **codes, not sentences**. E.g. a rejected upload
  returns `{ "error": "demo.file_too_large", "max_mb": 500 }`, not a Portuguese/English
  string. A failed parse stores status `parse_failed_corrupt`, not a human message.
- `frontend` (React) is the **single place** that maps codes → localized text, via
  `react-i18next` with `en.json` / `pt-BR.json` resource files + a language toggle.

Why: the two backend services never need to know the user's language, and translation logic
isn't duplicated across three codebases in two languages. One source of truth for words.

Deferred, low-cost when the time comes:
- react-i18next setup + language toggle + browser-language detection → frontend plumbing.
- Persisting a user's choice across devices → a `locale` column on `users` (one migration),
  add it alongside auth/teams.
