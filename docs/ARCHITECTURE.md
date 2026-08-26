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
                            ┌─────▼──────┐
                            │   nginx    │  (reverse proxy / gateway)
                            └─┬───┬───┬──┴──┐
                    /         │   │   │     │ /ollama-api/*  (study page)
        ┌─────────────────┐   │   │   │     └──────────────────────┐
        │    frontend     │◄──┘   │   │ /realtime/* (ws)           │
        │   React/Vite    │       │   └──────────┐                 │
        └─────────────────┘ /api/*▼              ▼                 ▼
                          ┌────────────────┐  ┌──────────────┐  ┌──────────────┐
                          │      api       │  │   realtime   │  │    ollama    │
                          │ Laravel (CRUD) │  │ tactics (Go) │  │ chat + embed │
                          └─┬───┬───┬───┬──┘  └───┬──────┬───┘  └──────▲───────┘
                      rpush │   │   │   │ http    │ pdo  │ token       │
                            │   │   │   └─────────┼──────┼─────────────┘
                       ┌────▼───┐   │ pdo         │      │
                       │ redis  │   └─────────────┼──────┼──────┐
                       └─▲───▲──┘                 │      │      │
              subscribe │   │ blpop · publish     ▼      ▼      ▼
        ┌───────────────┘   │              ┌─────────────────────────┐
   ┌────┴─────┐             │              │        postgres         │
   │ notifier │        ┌────┴─────────┐    │  + pgvector (vectors)   │
   └────┬─────┘        │    worker    │    └────────────▲────────────┘
        │ webhook      │  Go (parser) ├─────────────────┘ pdo
        ▼              └──┬────────┬──┘
     Discord     get/put  │        │ index
                ┌─────────▼──┐  ┌──▼───────────┐
                │   minio    │  │ meilisearch  │
                └────────────┘  └──────────────┘
```

Two of these services have **no inbound HTTP through nginx**: the `worker` is a pure
background consumer (its only input is the Redis queue), and the `notifier` consumes only
the events channel (its only output is a Discord webhook). The `realtime` service *is*
behind nginx but on a separate `/realtime/*` websocket route — it earns its own boundary
for a different reason than the worker does (see below). Redis carries both crossings
between the web world and the compute world: the **queue** (a command — api → worker) and
the **events channel** (facts — worker → whoever subscribes).

`ollama` is the newest box and the odd one out: it is **not a service this project wrote**,
it's a model runtime the api calls over plain HTTP, the way it calls Anthropic — one
`AnalystLlm`/`EmbeddingClient` contract, two possible implementations, chosen by env var
(see *Local models* below). Postgres gains a second role alongside CRUD: with the `pgvector`
extension it also stores the **embedding projection** the analyst retrieves over, so the
semantic read model needs no new datastore.

The one edge that breaks the rules is deliberate: nginx also exposes `/ollama-api/*`
straight through to `ollama:11434`, so the `/ollama-study` page can call the model
**from the browser with no backend in between**. That is a teaching route, not a product
route — it exists so the study page can show raw embeddings and raw token latency without
the api's prompt, retrieval, and grounding in the way. It is unauthenticated and would
have no place in a deployment (see *Local models* for why it is acceptable here).

Also not drawn: the observability containers (Alloy, Loki, Grafana, Jaeger) — they observe
every service but sit outside the data path (see Observability below) — and `ollama-init`,
a one-shot that pulls the embedding model at boot, plus `semgrep`, a `tools`-profile
scanner that runs on demand and exits.

## Why each boundary earns its place

### Go worker vs Laravel (the core split)

Demo parsing is CPU-bound and slow; the web app around it is ordinary CRUD. Two very
different workloads = the reason the boundary exists.

- **Go** has `demoinfocs-golang` (`github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs`),
  the mature Source 2 (CS2) parser, built for performance and concurrency. Requires Go 1.24+.
- **PHP could** parse a demo, but slowly, single-threaded, with no mature library.
- So Go exists *only* because of the parsing workload. That's a real justification, not a
  contrived one.

### Realtime service vs the Laravel api (a *second*, differently-justified split)

The tactics board is collaborative: several people drag pieces on a shared board and each
sees the others move in real time. That's a **long-lived, high-concurrency websocket**
workload — thousands of idle-but-open connections, tiny frequent messages, presence — which
is exactly what PHP-FPM's request-per-worker model is worst at. Go's goroutine-per-connection
model is built for it (`gorilla/websocket`, one hub, a goroutine per socket).

The important part for the study: this is a **different justification** from the worker.

- The **worker** is split because its work is *CPU-bound and slow* → async queue boundary.
- The **realtime** service is split because its work is *connection-bound and long-lived* →
  a persistent-connection boundary, still synchronous but over a socket, not the queue.

Two Go services, two unrelated reasons — a good reminder that "use Go" isn't the boundary;
the *workload shape* is. Note also it does **not** reuse the queue: realtime talks straight to
Postgres (load/save the board) and validates the caller by checking their Sanctum token
against the shared `personal_access_tokens` table. That cross-service auth-by-shared-table is
itself a deliberate shortcut (see its known-limitations note in `api/docs/domains/tactics.md`).

### Search: CQRS across a service boundary (Step 4)

Search introduces a **read model** that is a *projection*, not a source of truth. Postgres
(`kill_events`, `round_events`, written by the worker) stays authoritative; Meilisearch holds
a denormalized, query-optimized copy. This is CQRS made physical: writes and reads live in
different stores, kept in sync by projection, and **eventually consistent**.

- The **worker** projects: after it persists events to Postgres it indexes the same documents
  into Meilisearch. Indexing failure never fails a parse — the read model can lag or be
  rebuilt; the source of truth must not be held hostage to it.
- The read model is **fully rebuildable** from Postgres (`php artisan search:reindex`). That is
  the whole point of keeping the source of truth separate: the projection is disposable.
- The engine sits behind an **interface on both sides** — `App\Contracts\SearchIndex`
  (Laravel, for querying) and `internal/search.Indexer` (Go, for projecting). Swapping
  Meilisearch for Elasticsearch is rebinding one implementation per side, no domain changes.

Why a separate engine at all rather than Postgres `LIKE`/full-text: relevance-ranked
free-text + faceted filters at speed is a genuinely different workload from relational CRUD —
the same "earn the boundary" test the worker passed, applied to a datastore instead of a
runtime. The cost you take on in return is the one CQRS always charges: **staleness between
write and read**, and the operational duty to be able to rebuild the projection.

### The analyst: RAG, and a boundary that is a contract rather than a container

Asking an LLM about your matches is **mostly a retrieval problem**, and the retrieval half
is infrastructure this project already ran. The analyst is a thin loop — retrieve evidence,
paste it into the prompt, generate — so the interesting decisions are all about *which
evidence*, not about the model.

```
question ─▶ RETRIEVE  Postgres (recent visible matches + scoreboards)
        │             Meilisearch  — matches the question's WORDS
        │             pgvector     — matches its MEANING (matches, then rounds)
        ├─▶ AUGMENT   compact JSON evidence + question in one message
        └─▶ GENERATE  one call behind AnalystLlm
```

Keyword and vector search are **complements, not rivals**, projected from one source of
truth and failing in opposite directions: Meilisearch matches "AWP" exactly, pgvector
catches "our comeback games". Both are disposable projections of Postgres, the same CQRS
bargain search already made — and rebuildable for the same reason (`analyst:embed`,
`docs:embed`).

**No new datastore.** The vectors live in Postgres via `pgvector`, not in a dedicated
vector database. A vector column next to the rows it describes is one fewer service, one
fewer consistency problem, and at this corpus size an exact sequential scan is ~2ms. An
ivfflat index over the tiny corpus actually returned *zero rows* — approximate search lies
quietly at small scale. This is the "earn the boundary" test applied to a datastore, and
answered **no** for once: worth recording, because the reflex is to reach for Pinecone.

**Three corpora, one seam.** `match_embeddings` answers *which game*, `round_embeddings`
*which situation*, `doc_embeddings` *why the system is built this way* — the last a
question grep cannot answer, because the answer is an argument rather than a string. They
are three separate retriever contracts (`SemanticRetriever`, `RoundRetriever`,
`DocRetriever`), deliberately not one interface with a `corpus` parameter: matches and
rounds scope to what the caller may see, and documentation belongs to no match and no user,
so a shared parameter would have carried a scope argument meaningless to a third of its
callers. **The seam that genuinely generalized is `EmbeddingClient` — the one that never
had to change.**

The docs corpus is also served by a **separate endpoint**, not folded into the analyst:
`POST /api/docs/ask` (`AskDocsAction`, `DocsLlm`) sits outside every `can:` gate, because
the repository's own markdown is identical for every caller — there is no ownership to
check. `AskAnalystAction` never retrieves docs. Two corpora with different scoping rules
and different prompts turned out to be two loops that share an embedder, not one loop with
a parameter.

### Local models: the boundary that is a config value

The api talks to two model runtimes and does not know which one answered:

| Contract | Implementations | Chosen by |
|---|---|---|
| `AnalystLlm` | `AnthropicAnalyst` (Claude, metered) · `OllamaAnalyst` (`qwen2.5-coder:7b`, local) | `ANALYST_PROVIDER` |
| `DocsLlm` | `AnthropicDocsExplainer` · `OllamaDocsExplainer` (same models, different prompt) | `ANALYST_PROVIDER` |
| `EmbeddingClient` | `HashEmbeddings` (keyless stand-in, word overlap only) · `OllamaEmbeddings` (`nomic-embed-text`, 768d) | `EMBED_PROVIDER` |

There is no hosted embedder: embedding is the case where local won outright, so the hosted
slot was never filled. `HashEmbeddings` is the deliberately dumb placeholder that came
first — and it earned its keep, because shipping it meant the whole pipeline (column,
retriever, scoping, prompt) ran for real long before any model existed.

This is a service boundary that **never became a service**. `ollama` is a container, but
the api reaches it with the same HTTP call it makes to Anthropic, behind the same
interface — so "run the model locally" is one env var, not a migration. That is the
argument for interfaces stated precisely: not that you might replace a dependency someday,
but that you can **run the alternative today and compare**.

What running both actually measured (the full ledger is study topic 22):

- **For embeddings, local wins outright.** Batch work, small model, no per-call bill, no
  evidence about the team leaving the machine — and the thing it replaced was a
  deliberately dumb hash stand-in that could not tell "duel" from "fight".
- **For generation, local is the free and private option, not the good one.** The 7B model
  held the rules that matter structurally (cited real match ids, copied player names
  verbatim) and dropped the cosmetic ones. A model that fails the visible rules while
  keeping the invisible ones is easy to mistake for working correctly.
- **Whether it obeys a rule depends on the rule's *form*, not its importance.** Asked for
  `[doc:path#heading]` citations it produced zero; asked for `[1]` it complied immediately,
  and the numbers are expanded back into paths server-side. Design the output around what
  the model can emit, then translate.
- **Performance is a cliff, not a slope.** ~9s with the model resident on the GPU against
  ~265s on CPU for the same question. Ollama falls back to CPU whenever the model does not
  fit in VRAM and reports it nowhere except `ollama ps` — hence the deliberately generous
  600s timeout, which is the honest cost of running this locally rather than a bug.

**The projection is model-shaped.** Vectors from different models are mutually meaningless,
so switching embedders is three steps, not one: set `EMBED_PROVIDER` **and**
`EMBED_DIMENSIONS` together (hash is 256, `nomic-embed-text` is fixed at 768), migrate to
resize the column — which wipes the old vectors — then `analyst:embed` to rebuild.
Disposable-by-design is what makes a wipe routine rather than frightening.

**The one wire that skips every boundary is a teaching route.** `/ollama-api/*` proxies the
browser straight to `ollama:11434`, so the `/ollama-study` page can show raw embeddings and
raw latency with no api, no prompt, and no retrieval in between. nginx normalizes `Host` and
`Origin` because Ollama refuses non-localhost origins — meaning **nginx is doing the access
control that Ollama's own check would have done**, and it is doing none. That is fine for a
local study page and would be indefensible exposed: a model runtime with no auth is an open
compute endpoint. Recorded here rather than quietly fixed, because the shortcut is only safe
while the deployment story stays "one laptop".

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

### Event-driven notifications: commands vs events (Step 5)

```
worker ──PUBLISH──► redis channel ──SUBSCRIBE──► notifier ──https──► Discord webhook
```

The parse queue carries **commands** ("parse this") — one producer telling exactly one
consumer to do work. The events channel carries **facts** ("this happened") — the worker
PUBLISHes `match.parsed` / `match.failed` to a Redis pub/sub channel (`clutch_events`)
and does not know who listens. The `notifier` service subscribes and posts to a Discord
webhook; a future subscriber (email, cache warmer, a re-homed search projection) can join
without the worker changing. Publishers who don't know their subscribers — the pattern.

Event contract (cross-language, one-to-many — **additive changes only**, bump `v` for
breaking ones; publisher `worker/internal/events`, subscriber `notifier/internal/sub`,
same commit when it changes):

```json
{ "event": "match.parsed", "v": 1, "match_id": 42, "demo": "x.dem", "map": "de_mirage", "score_ct": 13, "score_t": 9, "traceparent": "00-…" }
```

(`traceparent` is optional W3C trace context — see Observability below; it lets the
subscriber's span join the publisher's trace across the channel.)

The deliberate weaknesses, kept visible:

- **Fire-and-forget**: pub/sub reaches only subscribers connected at that instant. A
  notifier restart loses the events in the gap — acceptable for pings, and exactly the
  at-most-once lesson. The earned upgrade is Redis Streams (consumer groups + acks)
  behind the same `Publisher`/subscriber interfaces.
- **Dual write**: the worker writes Postgres, then publishes; a crash between the two
  loses the event (the status row stays correct — it is the source of truth). The
  industrial fix is a transactional outbox; accepting the gap is a documented decision.
- Publishing is best-effort by the same rule as search indexing: a notification must
  never fail a parse.

### Data ownership (a deliberate later refactor)

Start with a **shared database** between api and worker — simpler, lets you learn the queue
first. Then *deliberately* refactor to separate databases so you feel what breaks:
- how does the frontend show "my match + its stats" when they live in two services?
- API composition (Laravel calls Go's read API and stitches) vs denormalization (duplicate a
  summary into Laravel's DB, eventual consistency)?

There's no free answer — that *is* the lesson. Feeling the pain of the shared-DB version
first is more educational than starting "correct."

## Observability (logs + traces)

Service boundaries scatter one action's story across processes — a monolith answers
"what happened to match 18" with a stack trace; this system needs tooling. Two layers,
both self-hosted in compose, zero external cost:

**Logs — Loki + Alloy + Grafana** (`observability/`). Alloy tails every container's
stdout via the Docker socket and labels lines by compose service; Loki stores them
(indexing only the labels — keep labels low-cardinality, per-match data belongs in the
log body); Grafana queries them at `localhost:3001`. `match_id` is the de-facto
correlation id: `{project="clutchlab-project"} |= "match 18"` reconstructs one match's
story across api, worker, and notifier. No service knows the pipeline exists.

**Traces — OpenTelemetry → Jaeger** (`localhost:16686`). One trace now follows an upload
across all three services. The api (Laravel) starts the root span on upload/reparse and
injects a W3C `traceparent` into the parse job; the worker extracts it so `parse_job`
(child spans download → parse → save → index) joins that trace rather than starting a new
one; the worker in turn injects a `traceparent` into the event it publishes, and the
notifier extracts it so its `notify` span joins too. Both non-HTTP hops (the queue list
and the pub/sub channel) carry the context in the payload — the non-HTTP version of the
`traceparent` header — and Go and PHP share one OTLP endpoint and propagator, so the
whole path reassembles as one waterfall in Jaeger. Tracing is observability, not
behavior: exports are batched/async and an unreachable Jaeger never affects a request or
a parse. Metrics and cross-service log correlation beyond `match_id` are the named next
rungs — tracing is the one pillar built.

## Testing the boundaries

The test strategy mirrors the architecture: the most valuable tests sit **on the seams**,
not inside the services.

| Layer | Where | What it proves |
|---|---|---|
| Contract tests | `contracts/*.json` + a test on each side | The wire format. The producer asserts **exact bytes** (it defines the canonical serialization); the consumer asserts the fixture decodes. Break either side and that side's suite goes red — the "change both sides in the same commit" rule, machine-enforced. |
| Feature tests | `api/tests/Feature` | The domain docs as assertions: the 403-not-404 authorization matrix, upload gating, content-hash dedup, team reassignment. In-memory SQLite; every external system (`DemoStorage`, `ParseQueue`, `SearchIndex`) replaced by a fake bound over its interface in the base `TestCase`. |
| Unit tables | Go `*_test.go` | Pure logic as a spec — the notifier's `message()` rendering, event marshaling (omitempty behavior is contract). |
| CI | `.github/workflows/` | Per-service jobs with **path filters** (the monorepo's promised middle path: independent test runs, atomic commits kept). One workflow per service — `api` (Pint + phpunit), `frontend` (lint + build), `worker` / `notifier` / `realtime` (vet + test) — plus `infra` (terraform fmt + validate, no cloud account). `contracts/**` is in every service's trigger paths *that speak a fixture*, so a fixture change runs every suite that decodes it (realtime and infra carry none, so they're excluded). |

Consumers of *events* deliberately tolerate unknown fields (additive one-to-many contract);
the *queue* consumer uses `DisallowUnknownFields` (point-to-point, same-commit rule — drift
should scream).

Two hard-won rules about tests and environments:

- **Containers leak env into tests.** Compose `env_file` lands in `$_SERVER`; Laravel's
  `env()` reads `$_SERVER` before `$_ENV`; PHPUnit's `<env>` (even `force="true"`) never
  touches `$_SERVER`. Without `<server>` overrides in `phpunit.xml`, `RefreshDatabase`
  migrate:freshes the **real** Postgres — it happened. `tests/TestCase` now hard-fails on
  any non-sqlite connection; keep both defenses forever.
- **Lockfiles remember their platform.** `package-lock.json` written in the musl (Alpine)
  dev container makes `npm ci` on a glibc runner skip every native binding (npm/cli#4828);
  the frontend workflow installs each musl package's gnu twin at the locked version.

Both incidents were the same shape — config resolved in one environment, executed in
another, failing silently. When an environment assumption matters, install a tripwire that
fails loudly instead of trusting the plumbing.

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
