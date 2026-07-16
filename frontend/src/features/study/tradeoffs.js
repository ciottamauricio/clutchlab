// The engineering study behind Clutchlab: every architectural decision as a ledger of what it
// *bought* and what it *cost*. Grounded in docs/ARCHITECTURE.md, docs/ROADMAP.md, and the
// per-domain docs — this is the real shape of the system, not a generic explanation.

export const TOPICS = [
  {
    id: 'seam',
    n: '01',
    title: 'Finding the seam',
    tag: 'principle',
    summary: 'A service boundary must be earned, not assumed.',
    gained: [
      'A single test for every candidate service: does this workload differ in a way a runtime boundary actually addresses?',
      'Avoids the classic trap — splitting by noun (users vs. matches) and drowning in plumbing.',
    ],
    paid: [
      'Discipline: "it\'s a different part of my domain" is not a reason to split. Most things stay a module inside one service.',
    ],
    body: [
      'The whole project studies the boundaries between services, not the features. So the guiding question is asked of every candidate: CPU-bound parsing, long-lived websockets, heavy scheduled aggregation may earn a service. A different noun in the domain almost never does — that is just a module.',
      'This is why Clutchlab is only as split as it needs to be: two Go services exist, and each is justified by a different workload shape, not by "use Go."',
    ],
  },
  {
    id: 'worker',
    n: '02',
    title: 'Go worker vs. Laravel',
    tag: 'the core split',
    summary: 'Parsing is CPU-bound and slow; the app around it is ordinary CRUD.',
    gained: [
      'demoinfocs-golang: the mature Source 2 (CS2) parser, built for performance and concurrency (needs Go 1.24+).',
      'The slow, heavy work is isolated from the request path — the web app never blocks on a parse.',
    ],
    paid: [
      'A second language and runtime to build, deploy, and reason about.',
      'PHP could parse a demo — but slowly, single-threaded, with no mature library. Go exists only because of this workload.',
    ],
    body: [
      'Demo parsing and web CRUD are two genuinely different workloads — that difference is the entire reason the boundary exists. The worker is a pure background consumer: its only input is the Redis queue, no inbound HTTP.',
      'The lesson is that "use Go" is not the boundary; the workload shape is. Go is chosen here for a real need, not for novelty.',
    ],
  },
  {
    id: 'realtime',
    n: '03',
    title: 'Realtime service vs. the api',
    tag: 'a second, different split',
    summary: 'The tactics board is collaborative — many people moving pieces on one shared board, live.',
    gained: [
      'Go\'s goroutine-per-connection model holds thousands of idle-but-open sockets cheaply (gorilla/websocket, one hub, room-per-tactic).',
      'A second async pattern next to the worker: connection-based streaming vs. queue-based batch.',
    ],
    paid: [
      'Cross-service auth by shared table: realtime validates the caller\'s Sanctum token against the shared personal_access_tokens table — a deliberate shortcut, noted as a limitation.',
      'A third service on the network, on its own /realtime/* websocket route.',
    ],
    body: [
      'This is split for a different reason than the worker. The worker is CPU-bound and slow, so it earns an async queue boundary. The realtime service is connection-bound and long-lived — thousands of open sockets, tiny frequent messages, presence — exactly what PHP-FPM\'s request-per-worker model is worst at. So it earns a persistent-connection boundary, still synchronous but over a socket, not the queue.',
      'Two Go services, two unrelated justifications — a good reminder that the runtime is never the boundary. It does not reuse the queue: it talks straight to Postgres to load and save the board.',
    ],
  },
  {
    id: 'sync-async',
    n: '04',
    title: 'Sync vs. async',
    tag: 'the queue boundary',
    summary: 'Parsing takes seconds to minutes — a synchronous call would hang and time out.',
    gained: [
      'The upload returns immediately; parsing happens out-of-band and the client polls a status field.',
      'You feel the real costs of async instead of reading about them.',
    ],
    paid: [
      'No immediate answer — the match needs a status / state machine (queued → parsing → parsed / failed).',
      'A worker crash mid-parse must be handled (retries, dead-letter). Messages may be delivered twice, so parsing must be idempotent.',
    ],
    body: [
      'A synchronous Laravel → Go call for a minutes-long parse would time out, which forces queue-based async communication — and with it every cost async carries. The CS2 use case makes those costs tangible: a real demo really does take that long.',
    ],
  },
  {
    id: 'polyglot-queue',
    n: '05',
    title: 'The polyglot queue tax',
    tag: 'redis',
    summary: 'The queue is a plain Redis list with JSON — not Laravel\'s native queue format.',
    gained: [
      'Two languages can share one channel: rpush a JSON blob on the PHP side, BLPOP + unmarshal on the Go side.',
      'The contract is explicit and visible, changed on both sides in the same commit.',
    ],
    paid: [
      'You give up Laravel\'s queue conveniences (queue:work, retries, batching) on this channel — its serialized PHP job objects Go can\'t read.',
      'You hand-roll the contract and keep both sides in sync by hand.',
    ],
    body: [
      'Laravel\'s native queue serializes PHP job objects that Go cannot deserialize. So the price of two languages sharing a queue is giving up those conveniences and owning the contract yourself. This is a real, visible tradeoff of the polyglot boundary — the design keeps it visible rather than hiding it.',
    ],
    code: '{ "match_id": 123, "demo_key": "demos/abc.dem" }',
    codeLabel: 'queue contract — keep both sides in sync',
  },
  {
    id: 'swappable-queue',
    n: '06',
    title: 'A list, not a broker — and swappable',
    tag: 'the boundary paying off',
    summary: 'The queue hides behind an interface, so the transport can change without touching the domain.',
    gained: [
      'The producer depends on a ParseQueue interface, not on Redis — swapping to RabbitMQ is a new RabbitMqParseQueue implements ParseQueue, rebound in one provider line.',
      'The Go side changes in one place too: BLPOP becomes an AMQP consume. No parsing, no domain, no HTTP touched on either side.',
      'You get to defer the hard choice: start with the simplest thing (a Redis list) and upgrade only when a real need is felt.',
    ],
    paid: [
      'A Redis list is a bare pipe: BLPOP removes the job before the work is done, so a worker that dies mid-parse loses it. Idempotent writes cover re-runs, but nothing re-delivers.',
      'The guarantees a real broker gives — acknowledgements, at-least-once delivery, dead-letter queues — are things you hand-roll or go without until you switch.',
    ],
    body: [
      'This is the whole study in one seam. Because the producer sits behind an interface, the transport is a detail, not a decision baked into the domain. RabbitMQ is the earned upgrade the moment you want acknowledgements and at-least-once delivery — the gap a bare list has. Kafka is a different tool entirely: a durable, replayable event log for pub-sub fan-out (the notifications path), not for handing one parse job to one worker.',
      'The lesson is not "use a broker" — it is that a boundary drawn in the right place makes the choice reversible. You can run the cheap thing now and swap it later for the cost of one class and one binding.',
    ],
    code: 'interface ParseQueue { public function push(int $matchId, string $demoKey): void; }',
    codeLabel: 'the seam — swap the implementation, not the domain',
  },
  {
    id: 'search-cqrs',
    n: '07',
    title: 'Search: CQRS across a boundary',
    tag: 'eventual consistency',
    summary: 'A read model that is a projection, not a source of truth.',
    gained: [
      'Relevance-ranked free-text + faceted filters at speed — a genuinely different workload from relational CRUD.',
      'The read model is fully rebuildable from Postgres (search:reindex); the projection is disposable.',
      'Both sides sit behind an interface (SearchIndex in Laravel, Indexer in Go) — swapping engines is rebinding one implementation per side.',
    ],
    paid: [
      'Staleness between write and read — the cost CQRS always charges.',
      'An operational duty: you must be able to rebuild the projection when it drifts or an index fails.',
    ],
    body: [
      'Postgres (kill_events, round_events) stays authoritative; Meilisearch holds a denormalized, query-optimized copy. This is CQRS made physical: writes and reads live in different stores, kept in sync by projection, and eventually consistent.',
      'The worker projects: after it persists events to Postgres it indexes the same documents into Meilisearch. Indexing failure never fails a parse — the source of truth must not be held hostage to the read model.',
    ],
  },
  {
    id: 'shared-db',
    n: '08',
    title: 'Shared database (for now)',
    tag: 'a deliberate later refactor',
    summary: 'api and worker share one Postgres — on purpose, to learn the queue first.',
    gained: [
      'Simplicity up front: no cross-service data composition to solve while you\'re still learning the async flow.',
      'A planned lesson: split the databases later and feel exactly what breaks.',
    ],
    paid: [
      'Two services coupled through a shared schema — the thing a clean SOA would avoid.',
      'The eventual split forces a hard question with no free answer: API composition (Laravel calls Go and stitches) vs. denormalization (duplicate a summary, accept eventual consistency).',
    ],
    body: [
      'Starting with a shared DB is deliberate. Feeling the pain of the shared-DB version first is more educational than starting "correct." The progression is the actual curriculum: monolith-ish → split under justified pressure → feel the tradeoffs → decide what was worth it.',
    ],
  },
  {
    id: 'authz',
    n: '09',
    title: 'Authentication & authorization',
    tag: 'sanctum + data-driven permissions',
    summary: 'Bearer tokens for auth; a runtime-editable grant matrix for authorization.',
    gained: [
      'Sanctum API tokens (Bearer) — the SPA sends Authorization: Bearer, guarded by auth:sanctum.',
      'Permissions are data, not code: abilities grant to roles across two scopes — team abilities resolve against the relevant team, app abilities gate whole pages.',
      'One PermissionService is the single source of truth; policies and gates delegate to it, and an admin edits the matrix live.',
    ],
    paid: [
      'Two authority axes to keep straight: a global role (member/admin) and a per-team role (owner/igl/player/coach).',
      'The client must be told what it may do — resources ship capability flags (can.delete, can.manage_members) so the UI hides what the server would refuse.',
      'The realtime service re-checks tokens against the shared table rather than calling the api — cross-service auth by shared state.',
    ],
    body: [
      'Auth answers "who are you" (a token); authorization answers "what may you do" (grants). Keeping them separate let authorization grow from hard-coded owner/igl checks into a data-driven system without touching the login path.',
      'The master admin is orthogonal to it all: a single Gate::before short-circuit that passes every check, so it can never lock itself out by editing the matrix.',
    ],
  },
  {
    id: 'i18n',
    n: '10',
    title: 'Codes, not sentences',
    tag: 'i18n boundary',
    summary: 'Backends return error codes; the frontend is the only place that speaks a language.',
    gained: [
      'The api and worker stay language-agnostic — a rejected upload returns demo.file_too_large, never a Portuguese or English string.',
      'Translation logic lives in one codebase (React), not duplicated across three services in two languages.',
    ],
    paid: [
      'Every backend error needs a stable code, and the frontend must map each one — an extra indirection over just returning a sentence.',
      'A user\'s language choice persists as a locale column on users, so it survives across devices.',
    ],
    body: [
      'The language boundary lives in the frontend; backend services stay language-agnostic. The two backends never need to know the user\'s language, and there is one source of truth for words. Decided up front because retrofitting i18n is painful, even though the full implementation is deferred until there\'s UI to translate.',
    ],
  },
  {
    id: 'monorepo',
    n: '11',
    title: 'Monorepo',
    tag: 'repo strategy',
    summary: 'One git repo; each service keeps its own dependency manifest.',
    gained: [
      'Atomic cross-service commits — change the queue JSON on both sides in one commit.',
      'One clone, one `up`. Services stay un-entangled: separate composer.json / package.json / go.mod.',
    ],
    paid: [
      'You forgo independent deploy cadence and per-repo access control that multiple repos would give.',
      'Those only matter under organizational or scale pressure — separate teams, huge tree — none of which apply to a solo learning project.',
    ],
    body: [
      'A monorepo in the "one git repo" sense, not a shared-tooling swamp. The compose file references ./api, ./frontend, ./worker as siblings. The middle path for later is per-service CI within the monorepo, triggered by path filters — independent build/test without losing atomic commits.',
    ],
  },
  {
    id: 'pub-sub',
    n: '12',
    title: 'Commands vs. events — the notifier',
    tag: 'pub/sub',
    summary: 'The queue tells one worker what to do; the event channel tells whoever cares what happened.',
    gained: [
      'The worker publishes match.parsed / match.failed to a Redis pub/sub channel without knowing who listens — the notifier turns them into Discord messages today; any future subscriber joins without touching the worker.',
      'A second messaging shape next to the queue: point-to-point commands (exactly one consumer must act) vs. broadcast facts (zero, one, or many may care).',
      'The notifier is the only backend that renders human sentences — it is the "frontend" for Discord, the same role React plays for the browser, so codes-not-sentences survives.',
    ],
    paid: [
      'Pub/sub is fire-and-forget: only subscribers connected at that instant receive the event. A notifier restart loses the gap — at-most-once delivery, felt rather than read about. Redis Streams (acks + consumer groups) is the earned upgrade behind the same interfaces.',
      'The dual write: the worker writes Postgres, then publishes. A crash between the two loses the event (the status row stays correct — it is the source of truth). The industrial fix is a transactional outbox; accepting the gap is a documented decision.',
      'One more cross-language contract, now one-to-many: unknown future subscribers mean additive changes only, with a version field for anything breaking.',
    ],
    body: [
      'The parse queue and the event channel look similar — both are JSON on Redis — but they are opposite ideas. A command ("parse this") is addressed to a role and must be consumed exactly once; losing one loses work. An event ("this happened") is addressed to no one; missing one loses only a notification. Matching the guarantee to the stakes is the lesson: parsing earns idempotent writes and a waiting queue, notifications get best-effort by design — an event must never fail a parse.',
      'The event also carries an optional W3C traceparent, so a subscriber\'s span joins the publisher\'s trace in Jaeger across the channel — the payload-borne version of the header HTTP services use.',
    ],
    code: '{ "event": "match.parsed", "v": 1, "match_id": 42, "map": "de_mirage", "score_ct": 13, "score_t": 9 }',
    codeLabel: 'the event contract — additive changes only, worker and notifier in the same commit',
  },
  {
    id: 'testing',
    n: '13',
    title: 'Testing the seams',
    tag: 'contracts, fakes, CI',
    summary: 'In a polyglot SOA the highest-leverage test is not a unit test — it is the wire contract, asserted from both sides.',
    gained: [
      'One fixture per cross-language message (contracts/): the producer asserts exact bytes, the consumer asserts it decodes — the "change both sides in the same commit" rule becomes machine-enforced. It caught a real bug on its first run: PHP escapes slashes in JSON, Go does not.',
      'Feature tests encode the domain docs as assertions (the 403 matrix, upload gating, content-hash dedup) on in-memory SQLite, with every external system swapped for a fake over its interface — the interface rule paying out again.',
      'CI runs per service with path filters (the monorepo topic\'s promised middle path), and contracts/** triggers every suite that speaks the fixture — a contract change cannot slip past one side.',
    ],
    paid: [
      'The environment seam bites hardest in tests. Compose env_file lands in $_SERVER, which Laravel reads before $_ENV — and PHPUnit\'s <env> overrides never touch $_SERVER, so the suite silently ran migrate:fresh on the real Postgres. Twice. The fix is <server> overrides plus a tripwire that refuses any non-sqlite connection.',
      'Same species, different layer: the npm lockfile written inside the musl (Alpine) dev container omits nothing, but npm ci on the glibc runner skips every native binding (npm/cli#4828) — rolldown, lightningcss, oxlint. The fix derives each musl package\'s gnu twin from the lock.',
      'A test suite is more code that must track the domain docs — drift in either direction is a lie.',
    ],
    body: [
      'The pyramid for this system, in order of leverage: contract tests at the seams (the thing this architecture uniquely needs), feature tests over HTTP encoding the rules the domain docs state as prose, table-driven tests of pure logic (the notifier\'s message(), one day the clutch detector), and eventually a single walking-skeleton E2E — one, not ten.',
      'The recurring lesson is that tests inherit every environment seam the services have. Both incidents above were the same shape: config resolved in one environment, executed in another, with no error — only wrong behavior. The tripwire pattern (fail loudly when the resolved environment is not the expected one) is cheaper than any recovery.',
    ],
    code: '{"match_id":123,"demo_key":"demos/abc.dem"}',
    codeLabel: 'contracts/parse_job.json — api asserts it produces these bytes; the worker asserts it decodes them',
  },
  {
    id: 'orchestration',
    n: '14',
    title: 'Orchestration: compose vs. cloud',
    tag: 'leaving the laptop',
    summary: 'docker-compose.yml does four jobs at once; in the cloud each job goes to a different owner.',
    gained: [
      'The service graph, the private network, env_file config, and restart policies — one file declares all of it, and `docker compose up` is the whole deployment.',
      'Cloud orchestrators (Container Apps, ECS, Kubernetes) do the lifecycle jobs better than compose: desired replica counts, health probes, automatic restarts, name-based service discovery.',
      'The stateful trio (Postgres, Redis, object storage) stops being your containers at all — it moves to managed services outside the orchestrator.',
    ],
    paid: [
      'depends_on does not exist in the cloud. Compose lets you cheat on startup order; an orchestrator starts services in any order and restarts them freely, so every service must retry its connections at boot instead of assuming Redis is already up.',
      'One file becomes several artifacts: container manifests for the orchestrator, secret-store entries replacing env_file, ingress rules replacing the nginx port mapping.',
      'Named volumes vanish — only genuinely stateful containers (Meilisearch here) still need a mounted disk; the rest must be stateless or gone.',
    ],
    body: [
      'The compose file is doing four jobs: declaring the service graph, wiring a private network where redis resolves by name, injecting config from the root .env, and supervising restarts. Locally that is one file; in the cloud each job has a different owner. Discovery survives everywhere — "services talk by name, never localhost" is exactly how Container Apps, ECS Service Connect, and Kubernetes DNS work — so that rule was cloud-ready from day one.',
      'The honest casualty is depends_on. It papers over startup ordering locally, and no orchestrator honors it. The cloud-ready posture is crash-and-retry: a consumer that tolerates its dependencies being briefly unreachable, not one that assumes the compose file ordered the world for it. Compose stays the local truth; a Terraform (or Bicep/Copilot) description becomes the cloud truth — and the diff between the two files is this whole topic made concrete. Both translations are written down in the repo: infra/azure (Container Apps) and infra/aws (ECS Fargate), the same architecture spelled twice.',
    ],
    code: 'depends_on: [postgres, redis, minio]  # compose-only comfort — no cloud equivalent',
    codeLabel: 'the line that does not survive the move',
  },
  {
    id: 'subscriber',
    n: '15',
    title: 'One fact, two frameworks — Laravel as subscriber',
    tag: 'events, mail',
    summary: 'The same training.scheduled fact now lands in Go (Discord) and Laravel (email) — the fan-out promise of topic 12, cashed in.',
    gained: [
      'A second subscriber joined the channel without the publisher changing a line: the Go notifier posts to Discord, the Laravel events-listener emails the roster. PUBSUB NUMSUB clutch_events reads 2 — one fact, two frameworks, verified live.',
      'The subscriber owns its own data. The event payload carries a player *count*, not addresses (a fact, not a data dump) — the email handler re-reads the roster by training_id at delivery time, so recipients are fresh even if the roster changed after publish.',
      'A rule for where a reaction lives: put it where its dependencies already are. Discord needs one HTTPS POST — a tiny Go daemon. Email needs the users table, Mailables, and codes→prose — all Laravel. Same subscriber shape, language chosen by the reaction.',
      'Reactions are a registry, not a switch: one EventHandler class per reaction, tagged in the provider — events:listen routes by each handler\'s handles(). Adding "email on match.failed" would touch zero existing files.',
    ],
    paid: [
      'A long-lived PHP daemon runs against the framework\'s request→die grain — memory creep and stale connections are the daemon\'s problem, not the framework\'s habit. Go gets this shape for free; Laravel needs the same care queue:work does.',
      'At-most-once, again: emails published while the listener is down are never sent. Tolerable for a practice invite; the day an email is *guaranteed*, that guarantee earns Redis Streams (acks + replay), not a bigger try/catch.',
      'The trigger chain is invisible until you know where to look. Nothing in the code says "TrainingScheduled → PublishTrainingScheduled" — the wiring IS the type-hint on the listener\'s handle(), matched by auto-discovery. Convention saves a config file and costs a grep; php artisan event:list is how you see the truth.',
    ],
    body: [
      'The full trigger is two hops. Hop one is in-process: CreateTrainingSessionAction saves the session and fires TrainingScheduled::dispatch($session) — a Laravel event that never leaves the process. Hop two is the boundary: an auto-discovered listener turns that private fact into the public one, publishing training.scheduled on clutch_events via the EventBus contract. Inside the process, convention and type-hints; across the boundary, an explicit versioned contract with a fixture. The formality jumps exactly at the seam.',
      'Why route email through Redis and back into the same framework that raised the event? Because in-process listeners only fire when Laravel itself did the thing. Coming back through the channel means any service — the Go worker, anything future — can cause an email by publishing a fact it would publish anyway. The listener is a general reaction pipeline, not a training-email feature; mail rides MAIL_MAILER=log until real credentials exist, the same log-only trick the notifier uses without a webhook URL.',
    ],
    code: 'public function handle(TrainingScheduled $event): void',
    codeLabel: 'the entire event→listener wiring — auto-discovery matches on this type-hint (see php artisan event:list)',
  },
]
