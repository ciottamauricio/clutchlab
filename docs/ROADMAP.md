# Roadmap

Features grouped by **the architectural muscle each one builds** — so every addition
introduces roughly one new concept instead of more of the same.

Suggested order (each step = one new concept):

1. **Core parser vertical slice** — upload → queue → parse → dashboard. Batch/async.
2. **Teams + auth/roles** — domain modeling + real authorization.
3. **Real-time tactics board** — websockets, a *second* Go service. Streaming.
4. **Search over parsed events** — a synced read model. CQRS / eventual consistency.
5. **Notifications + Discord bot** — event-driven pub-sub.
6. **Ask-the-analyst (RAG)** — retrieval over the read models you already built, generation
   behind an interface. The new concept is that *retrieval*, not the model, is the work.
7. **Local models** — a second implementation of the same contracts, chosen by env var, so
   "hosted vs local" becomes a measurement instead of an opinion.

Steps 1–7 are built. What follows is the unbuilt backlog.

---

## Stays in Laravel (CRUD — stretches domain modeling, not architecture)

- Teams with roles/permissions (owner / IGL / player / coach) — forces real authorization,
  which the app dodges at first.
- Practice scheduling + calendar.
- Comments, match sharing, public/private profiles.

Build when needed; know these aren't stretching the architecture.

## Extends the existing worker (more batch compute — same shape, deeper)

Each is just another `RegisterEventHandler` on the parser you already have. Lesson: reuse of
a service boundary — one parse pass, many analyses. Eventually job orchestration when one
upload triggers several analyses.

- Automatic utility-lineup extraction (every smoke/flash + where it landed).
- Death heatmaps per map.
- Round-type classification (eco / force / full-buy).
- Clutch detection.

## New service boundaries (the valuable ones)

### Real-time collaborative tactics board  →  a SECOND Go service (`realtime`)

The tactics feature. Simplest version is CRUD (a `tactics` table, JSON describing the board)
and lives in Laravel. But the *valuable* version is collaborative real-time editing — like a
mini Figma where the whole team moves pieces together during review.

That's a completely different workload from the parser:
- parser = **batch** (fire and forget, result later)
- tactics board = **real-time, stateful, concurrent** (many people mutating shared state)

Go's cheap goroutines hold thousands of websocket connections; model it as a room-per-tactic,
broadcast each edit to everyone in the room. Building both gives you the two canonical async
patterns side by side: queue-based batch vs connection-based streaming.

Bonus cross-service link: connect a saved tactic to a real parsed round ("here's the round we
tried this execute and it failed"). The tactic lives in Laravel, the round event in the stats
data — a nice distributed-data question.

### Search  →  dedicated search service (Meilisearch / Elasticsearch)

"Every round where we lost a 5-on-3", "all my AWP opening kills on Mirage." Fed from parser
output. You now maintain a **second read model** that must stay in sync — CQRS / eventual
consistency you can see and touch.

### Notifications + Discord bot  →  event-driven pub-sub

CS teams live in Discord. Parser emits an event (`MatchParsed`) rather than knowing about
Discord; a separate notifier service reacts. Publishers who don't know their subscribers —
one of the most important distributed-systems patterns.

### Ask-the-analyst  →  RAG over the read models you already have  *(built)*

Free-text questions about your own matches — "how do we usually lose on Mirage?". The
lesson is the reframing: this is mostly a *retrieval* problem, and the retrieval half was
already standing (Meilisearch for words, Postgres for rows). Generation is the small new
part, behind an `AnalystLlm` interface, grounded so every claim cites a real match.

Semantic recall is `pgvector` **inside Postgres** — a deliberate "no" to the earn-the-
boundary test, since a vector column beside the rows it describes is one fewer service.
Three corpora came out of it (matches, rounds, the project's own docs), and the third is
what proved which seam generalized: `EmbeddingClient`, the one that never had to change.

### Local models  →  the same contracts, a different runtime  *(built)*

An `ollama` container implements `AnalystLlm`, `DocsLlm`, and `EmbeddingClient` alongside
the hosted Claude implementations; `ANALYST_PROVIDER` / `EMBED_PROVIDER` pick which answers.
No new service boundary — that is the point. Running both is what turned "how much worse is
local?" into evidence: for embeddings local wins outright, for generation it is the free and
private option rather than the good one. Full ledger in study topic 22.

Named next rungs: **streaming the analyst's answer** (`docs/plans/stream-the-analyst.md`) so
a 9s generation feels like progress rather than a hang.

### Delivery metrics (DORA)  →  measuring the pipeline, not the product  *(built)*

The four DORA metrics plus a parse-reliability SLO, computed from rows the system records
about itself: `deployments` (written by CI), `incidents` (the one manual input), and
`parse_events`. The lesson turned out to be about **seams, not metrics** — parse telemetry
needed no new transport at all, because the worker was already publishing every parse
outcome on `clutch_events`; the metric became one more subscriber to a fact already in
flight. The second lesson is negative space: three of the five metrics read "not measured"
because nothing deploys yet, and making them render honestly was more work (and more
valuable) than making them render a number. Details in `api/docs/domains/dora.md`.

### Analytics / trends layer  →  separate read/reporting path

"T-side win rate on Inferno over 30 days." Analytical (not transactional) access pattern —
excuse to explore OLAP vs OLTP, a reporting DB, or materialized views. Teaches why one
database rarely serves every workload.

## Ambitious / later: ML tendency detector  →  a THIRD language (Python)

"You always take the same route on this bombsite", win-probability per round. ML ecosystem
lives in Python, so this justifies a third language for a real reason. Same async-worker shape
as the Go parser, different runtime chosen for a real need.

---

By the end you'll have touched: batch processing, streaming/websockets, search indexing,
event-driven messaging, retrieval-augmented generation, and polyglot services — a full tour
of SOA, wrapped in something you'd actually use with your team.
