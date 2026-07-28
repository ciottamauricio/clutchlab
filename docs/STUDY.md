# The engineering study

> **Generated from [`frontend/src/features/study/tradeoffs.js`](../frontend/src/features/study/tradeoffs.js) — do not edit by hand.**
> Run `node scripts/gen-study.mjs` after changing the topics. This is the GitHub-readable
> mirror of the in-app Study page.

Clutchlab is a study of the **seams between services**, not the features. A boundary must
be *earned* — and it is never free. Each decision below is a ledger: what it **gained**, and
what it **cost**.

## Topics

1. [Finding the seam](#01--finding-the-seam) — *principle*
2. [Go worker vs. Laravel](#02--go-worker-vs-laravel) — *the core split*
3. [Realtime service vs. the api](#03--realtime-service-vs-the-api) — *a second, different split*
4. [Sync vs. async](#04--sync-vs-async) — *the queue boundary*
5. [The polyglot queue tax](#05--the-polyglot-queue-tax) — *redis*
6. [A list, not a broker — and swappable](#06--a-list-not-a-broker-and-swappable) — *the boundary paying off*
7. [Search: CQRS across a boundary](#07--search-cqrs-across-a-boundary) — *eventual consistency*
8. [Shared database (for now)](#08--shared-database-for-now) — *a deliberate later refactor*
9. [Authentication & authorization](#09--authentication-authorization) — *sanctum + data-driven permissions*
10. [Codes, not sentences](#10--codes-not-sentences) — *i18n boundary*
11. [Monorepo](#11--monorepo) — *repo strategy*
12. [Commands vs. events — the notifier](#12--commands-vs-events-the-notifier) — *pub/sub*
13. [Testing the seams](#13--testing-the-seams) — *contracts, fakes, CI*
14. [Orchestration: compose vs. cloud](#14--orchestration-compose-vs-cloud) — *leaving the laptop*
15. [One fact, two frameworks — Laravel as subscriber](#15--one-fact-two-frameworks-laravel-as-subscriber) — *events, mail*
16. [The pipeline as a sixth service](#16--the-pipeline-as-a-sixth-service) — *CI, path filters*
17. [RAG: retrieval you already had](#17--rag-retrieval-you-already-had) — *AI, read models*
18. [One request across four processes](#18--one-request-across-four-processes) — *tracing, OTel*

---

## 01 · Finding the seam

*principle*

**A service boundary must be earned, not assumed.**

**Gained**

- A single test for every candidate service: does this workload differ in a way a runtime boundary actually addresses?
- Avoids the classic trap — splitting by noun (users vs. matches) and drowning in plumbing.

**Paid**

- Discipline: "it's a different part of my domain" is not a reason to split. Most things stay a module inside one service.

The whole project studies the boundaries between services, not the features. So the guiding question is asked of every candidate: CPU-bound parsing, long-lived websockets, heavy scheduled aggregation may earn a service. A different noun in the domain almost never does — that is just a module.

This is why Clutchlab is only as split as it needs to be: two Go services exist, and each is justified by a different workload shape, not by "use Go."

> **In plain English.** The whole project is really a study of one question: when does a piece of the system deserve to be its own service? My rule is that a boundary has to be earned — a workload has to differ in a way that a separate process actually fixes, like being slow, or long-lived, or heavy. Just being "a different part of the app" isn't enough; that's only a reason to make a new folder, not a new service. Keeping that bar high is what stops the project from drowning in plumbing.

---

## 02 · Go worker vs. Laravel

*the core split*

**Parsing is CPU-bound and slow; the app around it is ordinary CRUD.**

**Gained**

- demoinfocs-golang: the mature Source 2 (CS2) parser, built for performance and concurrency (needs Go 1.24+).
- The slow, heavy work is isolated from the request path — the web app never blocks on a parse.

**Paid**

- A second language and runtime to build, deploy, and reason about.
- PHP could parse a demo — but slowly, single-threaded, with no mature library. Go exists only because of this workload.

Demo parsing and web CRUD are two genuinely different workloads — that difference is the entire reason the boundary exists. The worker is a pure background consumer: its only input is the Redis queue, no inbound HTTP.

The lesson is that "use Go" is not the boundary; the workload shape is. Go is chosen here for a real need, not for novelty.

> **In plain English.** Parsing a match demo is slow and CPU-heavy — it chews through a big file for seconds or minutes. The rest of the app is ordinary create-read-update-delete stuff. Those are two different kinds of work, so I split them: a Go worker does the heavy parsing, and a Laravel app does the everyday web stuff. Go is good at fast, parallel number-crunching; Laravel is good at building web features quickly. Each service gets to be good at its own job instead of one language compromising on both.

---

## 03 · Realtime service vs. the api

*a second, different split*

**The tactics board is collaborative — many people moving pieces on one shared board, live.**

**Gained**

- Go's goroutine-per-connection model holds thousands of idle-but-open sockets cheaply (gorilla/websocket, one hub, room-per-tactic).
- A second async pattern next to the worker: connection-based streaming vs. queue-based batch.

**Paid**

- Cross-service auth by shared table: realtime validates the caller's Sanctum token against the shared personal_access_tokens table — a deliberate shortcut, noted as a limitation.
- A third service on the network, on its own /realtime/* websocket route.

This is split for a different reason than the worker. The worker is CPU-bound and slow, so it earns an async queue boundary. The realtime service is connection-bound and long-lived — thousands of open sockets, tiny frequent messages, presence — exactly what PHP-FPM's request-per-worker model is worst at. So it earns a persistent-connection boundary, still synchronous but over a socket, not the queue.

Two Go services, two unrelated justifications — a good reminder that the runtime is never the boundary. It does not reuse the queue: it talks straight to Postgres to load and save the board.

> **In plain English.** The tactics board is something several people edit at the same time — everyone drags pieces around one shared board and sees each other's moves instantly. That's a completely different shape from a normal web request: instead of ask-and-answer, it's a long-lived open connection with constant little updates. Go handles thousands of those open connections cheaply, so the live board is its own small service. It's a second split, but for a different reason than the worker — this one is about staying connected, not about crunching.

---

## 04 · Sync vs. async

*the queue boundary*

**Parsing takes seconds to minutes — a synchronous call would hang and time out.**

**Gained**

- The upload returns immediately; parsing happens out-of-band and the client polls a status field.
- You feel the real costs of async instead of reading about them.

**Paid**

- No immediate answer — the match needs a status / state machine (queued → parsing → parsed / failed).
- A worker crash mid-parse must be handled (retries, dead-letter). Messages may be delivered twice, so parsing must be idempotent.

A synchronous Laravel → Go call for a minutes-long parse would time out, which forces queue-based async communication — and with it every cost async carries. The CS2 use case makes those costs tangible: a real demo really does take that long.

> **In plain English.** When you upload a demo, I don't make you sit and wait for it to parse — that could take minutes, and the request would just time out. Instead the upload returns immediately saying "got it, parsing now," and the heavy work happens in the background. The app drops a job on a queue, the worker picks it up when it's ready, and the page updates when it's done. It's the difference between waiting on hold and getting a callback.

---

## 05 · The polyglot queue tax

*redis*

**The queue is a plain Redis list with JSON — not Laravel's native queue format.**

**Gained**

- Two languages can share one channel: rpush a JSON blob on the PHP side, BLPOP + unmarshal on the Go side.
- The contract is explicit and visible, changed on both sides in the same commit.

**Paid**

- You give up Laravel's queue conveniences (queue:work, retries, batching) on this channel — its serialized PHP job objects Go can't read.
- You hand-roll the contract and keep both sides in sync by hand.

Laravel's native queue serializes PHP job objects that Go cannot deserialize. So the price of two languages sharing a queue is giving up those conveniences and owning the contract yourself. This is a real, visible tradeoff of the polyglot boundary — the design keeps it visible rather than hiding it.

```
{ "match_id": 123, "demo_key": "demos/abc.dem" }
```

*queue contract — keep both sides in sync*

> **In plain English.** The queue that connects the app and the worker is deliberately simple: just a plain list in Redis, with each job written as basic JSON. I didn't use Laravel's built-in queue system, because that format only makes sense to Laravel — and my worker is written in Go. By using the plainest possible format, both languages can read and write the same jobs with no translation layer. The cost is I gave up Laravel's automatic retries and had to define the format myself, but that's the price of two languages sharing one queue.

---

## 06 · A list, not a broker — and swappable

*the boundary paying off*

**The queue hides behind an interface, so the transport can change without touching the domain.**

**Gained**

- The producer depends on a ParseQueue interface, not on Redis — swapping to RabbitMQ is a new RabbitMqParseQueue implements ParseQueue, rebound in one provider line.
- The Go side changes in one place too: BLPOP becomes an AMQP consume. No parsing, no domain, no HTTP touched on either side.
- You get to defer the hard choice: start with the simplest thing (a Redis list) and upgrade only when a real need is felt.

**Paid**

- A Redis list is a bare pipe: BLPOP removes the job before the work is done, so a worker that dies mid-parse loses it. Idempotent writes cover re-runs, but nothing re-delivers.
- The guarantees a real broker gives — acknowledgements, at-least-once delivery, dead-letter queues — are things you hand-roll or go without until you switch.

This is the whole study in one seam. Because the producer sits behind an interface, the transport is a detail, not a decision baked into the domain. RabbitMQ is the earned upgrade the moment you want acknowledgements and at-least-once delivery — the gap a bare list has. Kafka is a different tool entirely: a durable, replayable event log for pub-sub fan-out (the notifications path), not for handing one parse job to one worker.

The lesson is not "use a broker" — it is that a boundary drawn in the right place makes the choice reversible. You can run the cheap thing now and swap it later for the cost of one class and one binding.

```
interface ParseQueue { public function push(int $matchId, string $demoKey): void; }
```

*the seam — swap the implementation, not the domain*

> **In plain English.** Even though the queue is just a Redis list today, the rest of the code never talks to Redis directly — it talks to an interface, a kind of contract that says "put a job somewhere" without saying where. So the day I outgrow a plain list and want something sturdier, I swap the one piece behind that contract and nothing else changes. It's a small bit of discipline now that buys me a cheap upgrade later.

---

## 07 · Search: CQRS across a boundary

*eventual consistency*

**A read model that is a projection, not a source of truth.**

**Gained**

- Relevance-ranked free-text + faceted filters at speed — a genuinely different workload from relational CRUD.
- The read model is fully rebuildable from Postgres (search:reindex); the projection is disposable.
- Both sides sit behind an interface (SearchIndex in Laravel, Indexer in Go) — swapping engines is rebinding one implementation per side.

**Paid**

- Staleness between write and read — the cost CQRS always charges.
- An operational duty: you must be able to rebuild the projection when it drifts or an index fails.

Postgres (kill_events, round_events) stays authoritative; Meilisearch holds a denormalized, query-optimized copy. This is CQRS made physical: writes and reads live in different stores, kept in sync by projection, and eventually consistent.

The worker projects: after it persists events to Postgres it indexes the same documents into Meilisearch. Indexing failure never fails a parse — the source of truth must not be held hostage to the read model.

> **In plain English.** Some questions are painful to ask a normal database — "all my AWP opening kills on Mirage" would be a monster query. So I keep a second, search-optimized copy of the data in a tool built for exactly that. The important idea is that this copy is never the source of truth — it's a projection, rebuilt from the real database, and it can lag a moment behind. I trade perfect freshness for fast, flexible search, and I can always rebuild the copy from scratch if it drifts.

---

## 08 · Shared database (for now)

*a deliberate later refactor*

**api and worker share one Postgres — on purpose, to learn the queue first.**

**Gained**

- Simplicity up front: no cross-service data composition to solve while you're still learning the async flow.
- A planned lesson: split the databases later and feel exactly what breaks.

**Paid**

- Two services coupled through a shared schema — the thing a clean SOA would avoid.
- The eventual split forces a hard question with no free answer: API composition (Laravel calls Go and stitches) vs. denormalization (duplicate a summary, accept eventual consistency).

Starting with a shared DB is deliberate. Feeling the pain of the shared-DB version first is more educational than starting "correct." The progression is the actual curriculum: monolith-ish → split under justified pressure → feel the tradeoffs → decide what was worth it.

> **In plain English.** Right now the app and the worker share one database, and that's a deliberate choice, not laziness. Splitting the database is the harder, more advanced move, and I wanted to learn the queue boundary first without taking on two hard problems at once. I'm being honest that it's a shortcut, and I've written down exactly when and why I'd split it later. Naming a shortcut as a shortcut is part of the study.

---

## 09 · Authentication & authorization

*sanctum + data-driven permissions*

**Bearer tokens for auth; a runtime-editable grant matrix for authorization.**

**Gained**

- Sanctum API tokens (Bearer) — the SPA sends Authorization: Bearer, guarded by auth:sanctum.
- Permissions are data, not code: abilities grant to roles across two scopes — team abilities resolve against the relevant team, app abilities gate whole pages.
- One PermissionService is the single source of truth; policies and gates delegate to it, and an admin edits the matrix live.

**Paid**

- Two authority axes to keep straight: a global role (member/admin) and a per-team role (owner/igl/player/coach).
- The client must be told what it may do — resources ship capability flags (can.delete, can.manage_members) so the UI hides what the server would refuse.
- The realtime service re-checks tokens against the shared table rather than calling the api — cross-service auth by shared state.

Auth answers "who are you" (a token); authorization answers "what may you do" (grants). Keeping them separate let authorization grow from hard-coded owner/igl checks into a data-driven system without touching the login path.

The master admin is orthogonal to it all: a single Gate::before short-circuit that passes every check, so it can never lock itself out by editing the matrix.

> **In plain English.** Logging in uses a token — you sign in, get a token, and send it with each request to prove who you are. What you're allowed to do is separate, and I made it editable at runtime: instead of permissions being hard-coded, they live in a grant matrix an admin can change without a code deploy. So promoting someone to "can delete matches" is a setting, not a release. Auth answers "who are you"; this answers "what may you do" — and the second one shouldn't require a developer.

---

## 10 · Codes, not sentences

*i18n boundary*

**Backends return error codes; the frontend is the only place that speaks a language.**

**Gained**

- The api and worker stay language-agnostic — a rejected upload returns demo.file_too_large, never a Portuguese or English string.
- Translation logic lives in one codebase (React), not duplicated across three services in two languages.

**Paid**

- Every backend error needs a stable code, and the frontend must map each one — an extra indirection over just returning a sentence.
- A user's language choice persists as a locale column on users, so it survives across devices.

The language boundary lives in the frontend; backend services stay language-agnostic. The two backends never need to know the user's language, and there is one source of truth for words. Decided up front because retrofitting i18n is painful, even though the full implementation is deferred until there's UI to translate.

> **In plain English.** My backend services never return sentences meant for humans — they return short codes like "file too large." The frontend is the only place that turns a code into words a person reads, in their language. That keeps the two backends, in two languages, from each having to know about translations. There's one place words live, and the servers stay language-neutral. It's a little more indirection, but it means adding a new language never touches the backend.

---

## 11 · Monorepo

*repo strategy*

**One git repo; each service keeps its own dependency manifest.**

**Gained**

- Atomic cross-service commits — change the queue JSON on both sides in one commit.
- One clone, one `up`. Services stay un-entangled: separate composer.json / package.json / go.mod.

**Paid**

- You forgo independent deploy cadence and per-repo access control that multiple repos would give.
- Those only matter under organizational or scale pressure — separate teams, huge tree — none of which apply to a solo learning project.

A monorepo in the "one git repo" sense, not a shared-tooling swamp. The compose file references ./api, ./frontend, ./worker as siblings. The middle path for later is per-service CI within the monorepo, triggered by path filters — independent build/test without losing atomic commits.

> **In plain English.** Everything lives in one git repository — the app, the worker, the frontend, all of it — but each keeps its own separate dependency list. The payoff is that when I change something that spans two services, like the queue's job format, I fix both sides in a single commit that can never get half-applied. One clone, one command to run the whole thing, but the services still stay cleanly un-tangled from each other.

---

## 12 · Commands vs. events — the notifier

*pub/sub*

**The queue tells one worker what to do; the event channel tells whoever cares what happened.**

**Gained**

- The worker publishes match.parsed / match.failed to a Redis pub/sub channel without knowing who listens — the notifier turns them into Discord messages today; any future subscriber joins without touching the worker.
- A second messaging shape next to the queue: point-to-point commands (exactly one consumer must act) vs. broadcast facts (zero, one, or many may care).
- The notifier is the only backend that renders human sentences — it is the "frontend" for Discord, the same role React plays for the browser, so codes-not-sentences survives.

**Paid**

- Pub/sub is fire-and-forget: only subscribers connected at that instant receive the event. A notifier restart loses the gap — at-most-once delivery, felt rather than read about. Redis Streams (acks + consumer groups) is the earned upgrade behind the same interfaces.
- The dual write: the worker writes Postgres, then publishes. A crash between the two loses the event (the status row stays correct — it is the source of truth). The industrial fix is a transactional outbox; accepting the gap is a documented decision.
- One more cross-language contract, now one-to-many: unknown future subscribers mean additive changes only, with a version field for anything breaking.

The parse queue and the event channel look similar — both are JSON on Redis — but they are opposite ideas. A command ("parse this") is addressed to a role and must be consumed exactly once; losing one loses work. An event ("this happened") is addressed to no one; missing one loses only a notification. Matching the guarantee to the stakes is the lesson: parsing earns idempotent writes and a waiting queue, notifications get best-effort by design — an event must never fail a parse.

The event also carries an optional W3C traceparent, so a subscriber's span joins the publisher's trace in Jaeger across the channel — the payload-borne version of the header HTTP services use.

```
{ "event": "match.parsed", "v": 1, "match_id": 42, "map": "de_mirage", "score_ct": 13, "score_t": 9 }
```

*the event contract — additive changes only, worker and notifier in the same commit*

> **In plain English.** There are two very different kinds of messages in the system. The queue is a command — it tells one specific worker "parse this demo," and exactly one worker does it. The event channel is an announcement — it broadcasts "a match just finished" to whoever cares, and any number of listeners can react. Commands are one-to-one and about what to do; events are one-to-many and about what happened. Keeping them separate keeps the system loosely coupled — I can add a new reaction without touching whoever raised the event.

---

## 13 · Testing the seams

*contracts, fakes, CI*

**In a polyglot SOA the highest-leverage test is not a unit test — it is the wire contract, asserted from both sides.**

**Gained**

- One fixture per cross-language message (contracts/): the producer asserts exact bytes, the consumer asserts it decodes — the "change both sides in the same commit" rule becomes machine-enforced. It caught a real bug on its first run: PHP escapes slashes in JSON, Go does not.
- Feature tests encode the domain docs as assertions (the 403 matrix, upload gating, content-hash dedup) on in-memory SQLite, with every external system swapped for a fake over its interface — the interface rule paying out again.
- These tests need somewhere to run on every push — that pipeline is its own topic (16): six path-filtered workflows where contracts/** re-verifies every side of a fixture at once.

**Paid**

- The environment seam bites hardest in tests. Compose env_file lands in $_SERVER, which Laravel reads before $_ENV — and PHPUnit's <env> overrides never touch $_SERVER, so the suite silently ran migrate:fresh on the real Postgres. Twice. The fix is <server> overrides plus a tripwire that refuses any non-sqlite connection.
- Same species, different layer: the npm lockfile written inside the musl (Alpine) dev container omits nothing, but npm ci on the glibc runner skips every native binding (npm/cli#4828) — rolldown, lightningcss, oxlint. The fix derives each musl package's gnu twin from the lock.
- A test suite is more code that must track the domain docs — drift in either direction is a lie.

The pyramid for this system, in order of leverage: contract tests at the seams (the thing this architecture uniquely needs), feature tests over HTTP encoding the rules the domain docs state as prose, table-driven tests of pure logic (the notifier's message(), one day the clutch detector), and eventually a single walking-skeleton E2E — one, not ten.

The recurring lesson is that tests inherit every environment seam the services have. Both incidents above were the same shape: config resolved in one environment, executed in another, with no error — only wrong behavior. The tripwire pattern (fail loudly when the resolved environment is not the expected one) is cheaper than any recovery.

```
{"match_id":123,"demo_key":"demos/abc.dem"}
```

*contracts/parse_job.json — api asserts it produces these bytes; the worker asserts it decodes them*

> **In plain English.** In a system of several services in different languages, the scariest bug isn't inside one service — it's the two of them disagreeing about the message format between them. So my most valuable test isn't a normal unit test; it's a shared example of the wire format that both sides check themselves against. If someone changes the format on one side, the other side's test fails immediately. I test the seams between services, because that's where things actually break.

---

## 14 · Orchestration: compose vs. cloud

*leaving the laptop*

**docker-compose.yml does four jobs at once; in the cloud each job goes to a different owner.**

**Gained**

- The service graph, the private network, env_file config, and restart policies — one file declares all of it, and `docker compose up` is the whole deployment.
- Cloud orchestrators (Container Apps, ECS, Kubernetes) do the lifecycle jobs better than compose: desired replica counts, health probes, automatic restarts, name-based service discovery.
- The stateful trio (Postgres, Redis, object storage) stops being your containers at all — it moves to managed services outside the orchestrator.

**Paid**

- depends_on does not exist in the cloud. Compose lets you cheat on startup order; an orchestrator starts services in any order and restarts them freely, so every service must retry its connections at boot instead of assuming Redis is already up.
- One file becomes several artifacts: container manifests for the orchestrator, secret-store entries replacing env_file, ingress rules replacing the nginx port mapping.
- Named volumes vanish — only genuinely stateful containers (Meilisearch here) still need a mounted disk; the rest must be stateless or gone.

The compose file is doing four jobs: declaring the service graph, wiring a private network where redis resolves by name, injecting config from the root .env, and supervising restarts. Locally that is one file; in the cloud each job has a different owner. Discovery survives everywhere — "services talk by name, never localhost" is exactly how Container Apps, ECS Service Connect, and Kubernetes DNS work — so that rule was cloud-ready from day one.

The honest casualty is depends_on. It papers over startup ordering locally, and no orchestrator honors it. The cloud-ready posture is crash-and-retry: a consumer that tolerates its dependencies being briefly unreachable, not one that assumes the compose file ordered the world for it. Compose stays the local truth; a Terraform (or Bicep/Copilot) description becomes the cloud truth — and the diff between the two files is this whole topic made concrete. Both translations are written down in the repo: infra/azure (Container Apps) and infra/aws (ECS Fargate), the same architecture spelled twice.

```
depends_on: [postgres, redis, minio]  # compose-only comfort — no cloud equivalent
```

*the line that does not survive the move*

> **In plain English.** On my laptop, one docker-compose file quietly does four jobs at once: it builds the images, runs the containers, wires up the network, and holds the config. That's perfect for development. In the cloud, those four jobs split apart and go to four different owners — a registry, a runner, a network layer, config management. Understanding that one convenient file is really four concerns bundled together is the whole lesson of what it takes to leave the laptop.

---

## 15 · One fact, two frameworks — Laravel as subscriber

*events, mail*

**The same training.scheduled fact now lands in Go (Discord) and Laravel (email) — the fan-out promise of topic 12, cashed in.**

**Gained**

- A second subscriber joined the channel without the publisher changing a line — the Go notifier posts to Discord, the Laravel events-listener emails the roster. One fact, two frameworks, verified live (PUBSUB NUMSUB reads 2).
- Put a reaction where its dependencies already are: Discord is one HTTPS POST (a tiny Go daemon), email needs the users table and Mailables (Laravel). Same subscriber shape, language chosen by the reaction — and reactions are a registry, so "email on match.failed" would touch zero existing files.

**Paid**

- A long-lived PHP daemon runs against the framework's request→die grain — memory creep and stale connections need the same care queue:work does, which Go gets for free.
- At-most-once again: emails published while the listener is down are never sent. Fine for a practice invite; a guaranteed email would earn Redis Streams (acks + replay), not a bigger try/catch.

The trigger is two hops. In-process: CreateTrainingSessionAction fires TrainingScheduled, a Laravel event that never leaves the process. At the boundary: an auto-discovered listener turns that private fact into the public training.scheduled on clutch_events. Inside the process, convention and type-hints; across the seam, an explicit versioned contract with a fixture — the formality jumps exactly at the boundary. Routing email back through Redis (rather than a direct in-process call) is deliberate: it means any service, not just Laravel, can cause an email by publishing a fact it would publish anyway.

```
public function handle(TrainingScheduled $event): void
```

*the entire event→listener wiring — auto-discovery matches on this type-hint (see php artisan event:list)*

> **In plain English.** When a training gets scheduled, two completely different things need to happen: post to Discord, and email the players. Rather than cram both into one place, I announce the fact once on the event channel, and two independent listeners react — a tiny Go service posts to Discord, and Laravel sends the emails. The neat part is the announcer didn't change at all to add the second reaction. Each reaction lives where its tools already are, and I can add a third tomorrow without touching the other two.

---

## 16 · The pipeline as a sixth service

*CI, path filters*

**In a monorepo of six runtimes, CI is not one green check — it is six independent pipelines that only wake for what changed.**

**Gained**

- One workflow per buildable thing (api, frontend, each Go service, infra), triggered only by its own directory — a frontend commit never spends CI minutes on the Go services.
- contracts/** is the deliberate exception: it sits in every fixture-consumer's trigger paths, so one wire-contract change fans out to re-verify all sides — the "same commit, both sides" rule, enforced by the trigger graph itself.

**Paid**

- The Pint gate is a one-way door: the whole codebase must pass it first, so turning it on came with a repo-wide reformat commit. A gate is only honest if nothing is grandfathered past it.
- Path filters cut both ways — a shared change (root .env, docker-compose.yml) matches no service filter and triggers nothing. The filter graph models the dependency graph, and models drift. And this is still CI, not CD: nothing yet ships images.

The single-service instinct is one pipeline that builds everything; in a polyglot monorepo that wastes minutes on untouched services and couples their fates — a flaky Go test blocking a CSS fix. One pipeline per service, gated by path filters, keeps build isolation and atomic cross-service commits together, so CI stops being a wall at the end and becomes part of the architecture. The test pyramid (topic 13) is what runs; this is where and when — and they meet at contracts/**, where the highest-leverage test and the highest-leverage trigger are the same fixture.

```
paths: ["worker/**", "contracts/**", ".github/workflows/worker.yml"]
```

*a service wakes for its own code, the shared contracts, or its own pipeline definition — nothing else*

> **In plain English.** Because everything is in one repo but they're really six separate services, my automated checks aren't one big pass-or-fail — they're six independent pipelines, and each only runs when its own files change. A frontend tweak doesn't waste time re-testing the Go services. The one clever exception is the shared message formats: changing those triggers every service that uses them, so both sides get re-checked together. The pipeline ends up mirroring the same boundaries the services have.

---

## 17 · RAG: retrieval you already had

*AI, read models*

**Asking an LLM about your matches is mostly a retrieval problem — and the retrieval half is infrastructure this project already ran.**

**Gained**

- The analyst is a thin loop — retrieve evidence, paste it in the prompt, generate. The model knows nothing about your team; the real work is which evidence to fetch.
- Two retrievers cover each other's blind spots: keyword (Meilisearch) matches words exactly; semantic (pgvector) matches meaning, catching "our comeback games" and reaching past the recency window.
- Every part is a seam behind a contract — generator, embedder, store all swap without touching the action, so swapping the crude embedder for a real one is a recipe (set EMBED_DIMENSIONS, migrate, re-embed), not a rewrite.

**Paid**

- The default embedder is the hashing trick: keyless and local, but only as smart as word overlap — "duel" and "fight" miss. The plumbing is production-shaped; the intelligence is a placeholder built to be replaced.
- An ivfflat index on the tiny corpus returned zero rows — approximate search lies quietly at small scale, so it's a plain exact scan until a full scan actually hurts.

Every RAG project starts at "how do I use an LLM here", but the useful reframing is "what do I retrieve" — and Clutchlab answered most of that before the word came up: the search read model (topic 04) and Postgres already held the evidence. Generation was the small new part, behind an interface, grounded so every claim cites a real [match:N]. Adding semantic search made the two-read-model idea concrete: keyword and vector search aren't rivals but complements from the same source of truth, failing in opposite directions — the boundary discipline the whole project studies, pointed at AI.

```
semantically_related_matches: retriever.related(question, visibleIds, 5)
```

*the semantic retriever searches the whole visible set by meaning — it can surface a match older than the recent-window the keyword pass sees*

> **In plain English.** I built a feature that answers questions about our matches in plain English. The AI doesn't know our data, so instead of asking it to recall, I retrieve the relevant matches, kills, and trainings from our own database, hand them to the model along with the question, and let it write a grounded answer that cites each match. I fetch evidence two ways — by exact keyword and by meaning — so it catches both "AWP kills on Mirage" and vaguer questions like "our comeback games." That's RAG: retrieval-augmented generation — an open-book exam for the AI.

---

## 18 · One request across four processes

*tracing, OTel*

**Logs already reassemble one upload's story by match_id; tracing adds what they can't — the causal, timed waterfall across three languages and four processes.**

**Gained**

- A single trace follows one upload end to end — the api's enqueue span, the worker's download/parse/save/index, and the notifier's send, drawn as one waterfall in Jaeger. Verified live: one trace id, services [api, worker, notifier].
- The trace crosses non-HTTP hops the same way HTTP does — a W3C traceparent rides the queue job and the event JSON, so a consumer's span joins the producer's. Same OTLP endpoint and propagator in Go and PHP, so two languages share one trace.

**Paid**

- Tracing must never change behavior: an unreachable collector can't slow a request or fail an upload, so spans are batched and export failures are swallowed. That means a broken collector fails silently — the observability tool has no observability of its own.
- Two of three pillars now exist — logs (Loki/Alloy/Grafana, correlated by match_id) and traces — but not metrics: there are no rates, latencies, or error budgets, so you can see one request in detail yet not whether the system is healthy in aggregate. And logs and traces aren't linked (no shared trace_id in log lines), so jumping from a slow span to its logs is still manual.

A monolith answers "what happened here" with one log file. Split the work across an api, a Go worker, and a notifier and that question fragments across three of them. The logs pillar (Loki/Alloy/Grafana) already reassembles the story by grepping a shared match_id — good enough for "show me everything about match 18." Tracing adds what logs can't: causality and timing — not just that the three services acted, but that the parse was a child of the upload and took 1.2s of it, drawn as a waterfall. The propagation rule mirrors the events themselves (topic 12): across the boundary the context is explicit, carried in the payload, not implicit in a framework. The honest gap is metrics — the third pillar — plus linking a log line to its trace; logs and traces are built, aggregate health is not.

```
job['traceparent'] = tracing.traceparent()  // rides the queue into the worker
```

*the api injects its span context into the parse job; the worker extracts it so parse_job becomes a child of the upload — the cross-language hand-off*

> **In plain English.** When you upload a demo, the work touches three separate services in two languages — the app takes the file, a Go worker parses it, and another service posts to Discord. I already collect everyone's logs in one place and can pull up a single match's story by its ID. But logs tell you what happened, not how long each step took or what caused what. So I added tracing: every upload gets a trace ID that travels with it across all three services — riding inside the queue message and the event, since these don't talk over normal web requests — and they report their timing to one timeline I can open as a waterfall. What's still missing is the third piece, metrics: dashboards for overall rates and errors. Logs and traces are in; aggregate health is the next step.

---
