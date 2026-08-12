// The engineering study behind Clutchlab: every architectural decision as a ledger of what it
// *bought* and what it *cost*. Grounded in docs/ARCHITECTURE.md, docs/ROADMAP.md, and the
// per-domain docs — this is the real shape of the system, not a generic explanation.
//
// This is the single source of truth for the study. docs/STUDY.md (the GitHub-readable
// version) is generated from here — after editing topics, run `node scripts/gen-study.mjs`.

// Display order of the discipline groups. Topics keep their narrative numbers (01–20) as
// identity; this only controls how they're clustered on the page, in the deck, and in
// docs/STUDY.md. A topic's `group` must be one of these.
export const GROUPS = [
  'Software Architecture',
  'AI & Data',
  'DevOps & Delivery',
  'Cybersecurity',
  'Frontend & UX',
]

// Return topics grouped for display: [{ group, topics: [...] }, …] in GROUPS order, each
// group's topics in their original (numeric) order. One source of truth for every surface.
export function groupedTopics() {
  return GROUPS
    .map((group) => ({ group, topics: TOPICS.filter((t) => t.group === group) }))
    .filter((g) => g.topics.length > 0)
}

export const TOPICS = [
  {
    id: 'seam',
    group: 'Software Architecture',
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
    group: 'Software Architecture',
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
    group: 'Software Architecture',
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
    group: 'Software Architecture',
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
    group: 'Software Architecture',
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
    group: 'Software Architecture',
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
    group: 'Software Architecture',
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
    group: 'Software Architecture',
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
    group: 'Cybersecurity',
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
    group: 'Frontend & UX',
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
    group: 'Software Architecture',
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
    group: 'Software Architecture',
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
    group: 'DevOps & Delivery',
    n: '13',
    title: 'Testing the seams',
    tag: 'contracts, fakes, CI',
    summary: 'In a polyglot SOA the highest-leverage test is not a unit test — it is the wire contract, asserted from both sides.',
    gained: [
      'One fixture per cross-language message (contracts/): the producer asserts exact bytes, the consumer asserts it decodes — the "change both sides in the same commit" rule becomes machine-enforced. It caught a real bug on its first run: PHP escapes slashes in JSON, Go does not.',
      'Feature tests encode the domain docs as assertions (the 403 matrix, upload gating, content-hash dedup) on in-memory SQLite, with every external system swapped for a fake over its interface — the interface rule paying out again.',
      'These tests need somewhere to run on every push — that pipeline is its own topic (16): six path-filtered workflows where contracts/** re-verifies every side of a fixture at once.',
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
    group: 'DevOps & Delivery',
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
    group: 'DevOps & Delivery',
    n: '15',
    title: 'One fact, two frameworks — Laravel as subscriber',
    tag: 'events, mail',
    summary: 'The same training.scheduled fact now lands in Go (Discord) and Laravel (email) — the fan-out promise of topic 12, cashed in.',
    gained: [
      'A second subscriber joined the channel without the publisher changing a line — the Go notifier posts to Discord, the Laravel events-listener emails the roster. One fact, two frameworks, verified live (PUBSUB NUMSUB reads 2).',
      'Put a reaction where its dependencies already are: Discord is one HTTPS POST (a tiny Go daemon), email needs the users table and Mailables (Laravel). Same subscriber shape, language chosen by the reaction — and reactions are a registry, so "email on match.failed" would touch zero existing files.',
    ],
    paid: [
      'A long-lived PHP daemon runs against the framework\'s request→die grain — memory creep and stale connections need the same care queue:work does, which Go gets for free.',
      'At-most-once again: emails published while the listener is down are never sent. Fine for a practice invite; a guaranteed email would earn Redis Streams (acks + replay), not a bigger try/catch.',
    ],
    body: [
      'The trigger is two hops. In-process: CreateTrainingSessionAction fires TrainingScheduled, a Laravel event that never leaves the process. At the boundary: an auto-discovered listener turns that private fact into the public training.scheduled on clutch_events. Inside the process, convention and type-hints; across the seam, an explicit versioned contract with a fixture — the formality jumps exactly at the boundary. Routing email back through Redis (rather than a direct in-process call) is deliberate: it means any service, not just Laravel, can cause an email by publishing a fact it would publish anyway.',
    ],
    code: 'public function handle(TrainingScheduled $event): void',
    codeLabel: 'the entire event→listener wiring — auto-discovery matches on this type-hint (see php artisan event:list)',
  },
  {
    id: 'pipeline',
    group: 'DevOps & Delivery',
    n: '16',
    title: 'The pipeline as a sixth service',
    tag: 'CI, path filters',
    summary: 'In a monorepo of six runtimes, CI is not one green check — it is six independent pipelines that only wake for what changed.',
    gained: [
      'One workflow per buildable thing (api, frontend, each Go service, infra), triggered only by its own directory — a frontend commit never spends CI minutes on the Go services.',
      'contracts/** is the deliberate exception: it sits in every fixture-consumer\'s trigger paths, so one wire-contract change fans out to re-verify all sides — the "same commit, both sides" rule, enforced by the trigger graph itself.',
    ],
    paid: [
      'The Pint gate is a one-way door: the whole codebase must pass it first, so turning it on came with a repo-wide reformat commit. A gate is only honest if nothing is grandfathered past it.',
      'Path filters cut both ways — a shared change (root .env, docker-compose.yml) matches no service filter and triggers nothing. The filter graph models the dependency graph, and models drift. And this is still CI, not CD: nothing yet ships images.',
    ],
    body: [
      'The single-service instinct is one pipeline that builds everything; in a polyglot monorepo that wastes minutes on untouched services and couples their fates — a flaky Go test blocking a CSS fix. One pipeline per service, gated by path filters, keeps build isolation and atomic cross-service commits together, so CI stops being a wall at the end and becomes part of the architecture. The test pyramid (topic 13) is what runs; this is where and when — and they meet at contracts/**, where the highest-leverage test and the highest-leverage trigger are the same fixture.',
    ],
    code: 'paths: ["worker/**", "contracts/**", ".github/workflows/worker.yml"]',
    codeLabel: 'a service wakes for its own code, the shared contracts, or its own pipeline definition — nothing else',
  },
  {
    id: 'rag',
    group: 'AI & Data',
    n: '17',
    title: 'RAG: retrieval you already had',
    tag: 'AI, read models',
    summary: 'Asking an LLM about your matches is mostly a retrieval problem — and the retrieval half is infrastructure this project already ran.',
    gained: [
      'The analyst is a thin loop — retrieve evidence, paste it in the prompt, generate. The model knows nothing about your team; the real work is which evidence to fetch.',
      'Two retrievers cover each other\'s blind spots: keyword (Meilisearch) matches words exactly; semantic (pgvector) matches meaning, catching "our comeback games" and reaching past the recency window.',
      'Every part is a seam behind a contract — generator, embedder, store all swap without touching the action, so swapping the crude embedder for a real one is a recipe (set EMBED_DIMENSIONS, migrate, re-embed), not a rewrite.',
    ],
    paid: [
      'The default embedder is the hashing trick: keyless and local, but only as smart as word overlap — "duel" and "fight" miss. The plumbing is production-shaped; the intelligence is a placeholder built to be replaced.',
      'An ivfflat index on the tiny corpus returned zero rows — approximate search lies quietly at small scale, so it\'s a plain exact scan until a full scan actually hurts.',
    ],
    body: [
      'Every RAG project starts at "how do I use an LLM here", but the useful reframing is "what do I retrieve" — and Clutchlab answered most of that before the word came up: the search read model (topic 04) and Postgres already held the evidence. Generation was the small new part, behind an interface, grounded so every claim cites a real [match:N]. Adding semantic search made the two-read-model idea concrete: keyword and vector search aren\'t rivals but complements from the same source of truth, failing in opposite directions — the boundary discipline the whole project studies, pointed at AI.',
    ],
    code: 'semantically_related_matches: retriever.related(question, visibleIds, 5)',
    codeLabel: 'the semantic retriever searches the whole visible set by meaning — it can surface a match older than the recent-window the keyword pass sees',
  },
  {
    id: 'observability',
    group: 'DevOps & Delivery',
    n: '18',
    title: 'One request across four processes',
    tag: 'tracing, OTel',
    summary: 'Logs already reassemble one upload\'s story by match_id; tracing adds what they can\'t — the causal, timed waterfall across three languages and four processes.',
    gained: [
      'A single trace follows one upload end to end — the api\'s enqueue span, the worker\'s download/parse/save/index, and the notifier\'s send, drawn as one waterfall in Jaeger. Verified live: one trace id, services [api, worker, notifier].',
      'The trace crosses non-HTTP hops the same way HTTP does — a W3C traceparent rides the queue job and the event JSON, so a consumer\'s span joins the producer\'s. Same OTLP endpoint and propagator in Go and PHP, so two languages share one trace.',
    ],
    paid: [
      'Tracing must never change behavior: an unreachable collector can\'t slow a request or fail an upload, so spans are batched and export failures are swallowed. That means a broken collector fails silently — the observability tool has no observability of its own.',
      'Two of three pillars now exist — logs (Loki/Alloy/Grafana, correlated by match_id) and traces — but not metrics: there are no rates, latencies, or error budgets, so you can see one request in detail yet not whether the system is healthy in aggregate. And logs and traces aren\'t linked (no shared trace_id in log lines), so jumping from a slow span to its logs is still manual.',
    ],
    body: [
      'A monolith answers "what happened here" with one log file. Split the work across an api, a Go worker, and a notifier and that question fragments across three of them. The logs pillar (Loki/Alloy/Grafana) already reassembles the story by grepping a shared match_id — good enough for "show me everything about match 18." Tracing adds what logs can\'t: causality and timing — not just that the three services acted, but that the parse was a child of the upload and took 1.2s of it, drawn as a waterfall. The propagation rule mirrors the events themselves (topic 12): across the boundary the context is explicit, carried in the payload, not implicit in a framework. The honest gap is metrics — the third pillar — plus linking a log line to its trace; logs and traces are built, aggregate health is not.',
    ],
    code: "job['traceparent'] = tracing.traceparent()  // rides the queue into the worker",
    codeLabel: 'the api injects its span context into the parse job; the worker extracts it so parse_job becomes a child of the upload — the cross-language hand-off',
  },
  {
    id: 'trust-boundary',
    group: 'Cybersecurity',
    n: '19',
    title: 'The trust boundary is the network',
    tag: 'security, threat model',
    summary: 'One container is the front door; everything behind it trusts the private network — a real boundary with a real cost, drawn like the others.',
    gained: [
      'A single attack surface: only nginx publishes to the host (8080). The api, both Go services, Postgres, Redis, MinIO, and Meilisearch are unreachable from outside — they answer only on the internal clutchnet, by service name. One thing to harden, not nine.',
      'Auth is layered at that door, not sprinkled: bearer tokens (Sanctum) gate every route past login; auth and mutation endpoints are rate-limited; ownership violations return 403 not 404; uploads are validated at the boundary (type, size, content-hash dedup) before a byte reaches the parser. The perimeter is where the checks live.',
    ],
    paid: [
      'Trust inside the network is implicit, and that is a real risk, not a free simplification: the worker parses whatever demo lands on the Redis list, the notifier trusts any event on the channel — no service authenticates another. It holds only because the network is sealed; anything that gets a foothold inside inherits that trust. Service-to-service auth (mTLS, signed jobs) is the earned upgrade, unbuilt.',
      'Developer convenience punches holes in the front-door story: Postgres (5432), MinIO (9000/9001), Meili (7700), Jaeger (16686), and an anonymous-admin Grafana (3001) all publish to the host too. Harmless on a laptop, a breach in production — so "only nginx is exposed" is a rule the dev compose already bends, and shipping means binding those to localhost or dropping them. Naming the gap is the point.',
    ],
    body: [
      'Security in a service-oriented app is a boundaries problem, which is why it belongs in this ledger. The model here is a hard shell around a soft interior: nginx is the one door, authenticated and rate-limited, and behind it the services trust each other because the Docker network is the wall. That is a legitimate, common posture — and stating it plainly is what makes its cost visible. The interior trust is the sharp edge: it is defense-in-depth with only one layer, so the day something slips inside (a poisoned demo crashing the parser, a leaked internal port), there is no second check to catch it. The honest security roadmap is the same shape as the rest of the project — the boundary is earned and real today, and the next rungs (sandboxing the untrusted-file parse, per-service auth, binding dev ports to localhost) are named, not yet built.',
    ],
    code: 'ports: ["8080:80"]   # nginx — the only service that publishes to the host',
    codeLabel: 'every other service omits ports: and is reachable only on the internal network — the trust boundary, in one line of compose',
  },
  {
    id: 'untrusted-parse',
    group: 'Cybersecurity',
    n: '20',
    title: 'Sandboxing the untrusted parser',
    tag: 'security, defense-in-depth',
    summary: 'A demo is attacker-controlled input fed to a native parser — the app\'s sharpest edge, hardened in three layers that each catch what the last cannot.',
    gained: [
      'Three layers, three failure modes: panic recovery turns a crashing demo into a failed job; resource limits (a wall-clock timeout + heap ceiling checked between frames) stop one that hangs or exhausts memory; process isolation runs the parse in a throwaway child (worker re-execs itself, demo on stdin, result JSON on stdout) so a hard crash or an exploited parser bug is confined to that process, not the worker.',
      'Isolation reuses a boundary that already existed. The worker was split from the api for a performance reason (CPU-bound parsing); the same split now doubles as a security boundary — the risky native work is already off in its own service, and the child process hardens it further. The child gets an empty environment: no DB creds, no S3 keys, only the bytes on stdin, so even a successful exploit inherits nothing.',
    ],
    paid: [
      'Isolation costs a process spawn per parse and a serialization round-trip (ParseResult back over a pipe as JSON) — negligible against a multi-second parse, but real, and it adds a moving part: the binary must be able to re-exec itself, which the air dev container can\'t do cleanly, so isolation is a prod-only flag and dev runs the in-process path. Two code paths to keep honest.',
      'It stops at the process. The child still shares the host kernel, network, and filesystem — a true jail (no network, read-only FS, seccomp, or a microVM) is the next rung. And a crashed child is reported as a generic corrupt parse; the parent can\'t always tell "hostile" from "broken" once the OS has killed the process for it.',
    ],
    body: [
      'Most of this project\'s boundaries are about structure; this one is about trust. Everything else assumes inputs are broadly well-formed, but the demo parser eats bytes a stranger uploaded, through a large native library that was never promised to be hostile-input-safe. That makes it the one place worth defense-in-depth: not one check but a stack, ordered so each layer handles the failure the previous can\'t see. Panic recovery was there first (it catches demos that error out). Resource limits came next (they catch the ones that don\'t error but never stop). Isolation is the floor under both — when a demo does something neither anticipated, the damage is a dead subprocess and a failed job, not a downed worker or a foothold in the network. The honest frame is that security is layers, not a wall: this is three of them, the OS-level jail is the fourth, and naming that gap is the same discipline the rest of the ledger uses.',
    ],
    code: 'exec.CommandContext(ctx, self, "--parse-child")  // parse in a throwaway process; Env: none',
    codeLabel: 'the worker re-execs itself to parse one demo in isolation — a crash or exploit dies with the child, and it inherits no secrets',
  },
  {
    id: 'split-db',
    group: 'Software Architecture',
    n: '21',
    title: 'Splitting the store: ownership over convenience',
    tag: 'data ownership, the event handoff',
    summary: 'The shared database topic 08 kept "for now" gets split along an ownership line — and the worker\'s direct UPDATE to a table it didn\'t own becomes an event to the table\'s owner.',
    gained: [
      'A real ownership line, enforced by Postgres: the analytics schema (match_player_stats, kill_events, round_events) is the worker\'s; matches stays the api\'s. Per-service DB roles scope each side to its own schema, so the worker is physically denied a write to matches — the wall is a grant, not a guideline.',
      'The convenience that had to go was the worker\'s direct UPDATE matches. It becomes a match.parsed event carrying the full summary; the api\'s events-listener applies it to the row it owns. The seam it crossed is the events channel that already existed (topic 12) — the split reused the pub/sub boundary rather than inventing one, and owner_id + filename now ride the parse job so the worker reads matches zero times.',
    ],
    paid: [
      'You lose the cross-boundary conveniences that were really loans against a shared DB: no foreign key from analytics.kill_events to matches, no join across the line (a dashboard stitches two reads in code), and the atomic "stats + status in one transaction" is now two steps — a DB write and a separate event. Phase 1 (schema + roles) and phase 2 (the event handoff) are coupled: revoking the write without removing the need for it just breaks the worker, so they land together.',
      'The status handoff inherits pub/sub\'s at-most-once delivery: a dropped match.parsed leaves the row stuck in "parsing" — today\'s gap, where the old direct UPDATE was a guaranteed write. A reconciler (sweep stale "parsing" rows, or a durable stream) is the earned upgrade. And the role wall is only half-hung: the migration creates clutch_worker, but flipping the worker\'s connection onto it in compose is the last wire still to land.',
    ],
    body: [
      'Topic 08 shared one Postgres between the api and worker deliberately, to learn the queue before taking on a second hard thing. This is that second thing, cashed in — and the lesson is that splitting a database is not a schema chore but a question of who owns each fact. Draw that line and every convenience you had comes due: the foreign key, the join, the one-transaction write, and above all the worker\'s direct UPDATE to the api\'s matches table. That last one is the whole story in miniature — it was only ever possible because the two services shared a database, and the split converts it into a message to the owner. The events you already publish are how you pay the loan back without a distributed transaction. It went in as one logical unit (schema move, scoped roles, event handoff) because the pieces don\'t stand alone, and it leaves two honest rungs named: the reconciler for the dropped-event gap, and wiring the worker onto its scoped role so the wall is enforced in the running system, not just in the schema.',
    ],
    code: "// worker, post-split:  writes analytics.*  +  publishes match.parsed  (never touches matches)",
    codeLabel: 'the worker\'s old UPDATE matches became an event; the api owns the row and applies the summary from it',
  },
]
