# Analyst domain (RAG)

## Status

Implemented. A single endpoint that answers free-text questions about the caller's own
matches, using **RAG** (retrieval-augmented generation): retrieve evidence from the data
we already keep, hand it to Claude with the question, return grounded prose.

## Purpose

Answer cross-match questions no single dashboard shows — "how do we usually lose on
Mirage?", "who gets our opening kills?". The LLM never *knows* anything: it only
summarizes the evidence retrieved for this one request. The study subject here is that
RAG's retrieval half is infrastructure this project already had — the search read model
and the transactional tables — and only the generation half is new.

## The RAG loop

```
question ──▶ RETRIEVE  Postgres: newest 15 parsed visible matches + scoreboards
         │             Meilisearch `kills`: 40 hits matching the question's WORDS,
         │             scoped to those same matches (degrades to [] if down)
         │             pgvector `match_embeddings`: 5 nearest match cards by MEANING,
         │             over the whole visible set (degrades to [] if down)
         │             Postgres: the caller's teams' 10 most recent trainings —
         │             tactics drilled, roster + RSVPs, nade homework
         ├──▶ AUGMENT  compact JSON evidence + the question in one user message
         └──▶ GENERATE one Claude call (AnalystLlm), grounded by the system prompt
```

Matches and kills are **outcomes**; trainings are **intent**. The system prompt names
the split, so the analyst can connect "what we practiced" to "what happened" —
a question no single page in the app can answer. Trainings need no keyword
retrieval: the corpus is small and recency IS relevance for practice questions.

### Two retrievers, two jobs (keyword vs. semantic)

The analyst runs **two** retrievers on every question, because they fail differently:

- **Keyword** (`SearchIndex` / Meilisearch) matches the question's *words* against kill
  rows — precise for "awp opening kills on mirage", blind to paraphrase ("long-range
  duels" shares no words with any kill row, so it finds nothing).
- **Semantic** (`SemanticRetriever` / pgvector) embeds the question and finds match
  *cards* closest in *meaning* — "our comeback games", "close matches on Nuke". It
  searches the **whole visible set**, so it can surface a relevant match older than the
  15-match recency window. It's fuzzy: it always returns its nearest N with a
  `similarity` score, and the prompt tells the model to weight by that score.

Keyword is exact-but-literal; semantic is fuzzy-but-meaning-aware. Together they cover
each other's blind spots — the core lesson of this domain.

### The semantic read model (pgvector)

A third read model, same CQRS shape as search: Postgres matches are the source of
truth; `match_embeddings` is a **projection** — one embedded prose "card" per parsed
match (`BuildMatchCardAction`), rebuildable any time with `php artisan analyst:embed`.

The index **follows parses on its own**: `EmbedParsedMatch` is a second `match.parsed`
handler that embeds the one new card, so a freshly parsed match is semantically
searchable without anyone running the command. It is registered *after*
`ApplyMatchParsed` because the card is built from the row that handler writes, and it
re-checks `status === 'parsed'` rather than trusting registration order — embedding early
would store a contentless card ("unknown map", no score) that looks perfectly valid.
`analyst:embed` remains the bulk rebuild path, for an embedder swap or a backfill.

- **Embedding is a seam.** `EmbeddingClient` turns text → a fixed-width vector, and
  reports that width via `dimensions()`. The default `HashEmbeddings` is a keyless local
  embedder (the "hashing trick") — it runs the whole architecture with no external
  account, but captures word overlap, not meaning ("duel" and "fight" land in different
  buckets).

  **`OllamaEmbeddings` is the wired learned embedder** (`EMBED_PROVIDER=ollama`,
  `nomic-embed-text`, 768 dims), served by the local `ollama` container — no account, no
  per-call bill, nothing leaving the machine. It does what the hash stand-in can't:
  "long-range duels" scores 0.59 against "awp sniper fights" and 0.38 against "pistol
  round eco save", despite sharing no words with either.

  **Swapping in a different embedder** (Voyage, OpenAI, another Ollama model) is four
  steps, no changes to the retriever or action:
  1. Write the class implementing `EmbeddingClient`; its `dimensions()` returns the
     model's width (voyage-3 = 1024, nomic-embed-text = 768).
  2. Bind its case in the `EmbeddingClient` match in `AppServiceProvider`, and set
     `EMBED_PROVIDER` to select it.
  3. Set `EMBED_DIMENSIONS` to the model's width and `php artisan migrate` — the resize
     migration reshapes the `vector(N)` column (a no-op while the width is unchanged).
  4. `php artisan analyst:embed` — rebuild the index; the old vectors mean nothing to the
     new model, and the projection is disposable by design.

  `EMBED_DIMENSIONS` is the single value the column width and the embedder both read, so
  they can't silently disagree.
- **Storage is pgvector.** A `vector(256)` column; search is exact cosine distance
  (`<=>`) with a sequential scan. **No ANN index on purpose**: an ivfflat/hnsw index is
  *approximate* and on a tiny corpus its probes can miss the populated list and return
  zero rows (it did, during the build — the reason the note exists). A full scan is
  exact and instant at this scale; add an ivfflat index once the corpus is large enough
  that scanning hurts — the earned upgrade.

## Rules

1. **Generation sits behind an interface.** `App\Contracts\AnalystLlm` (one method:
   `answer(question, evidence)`); `App\Llm\AnthropicAnalyst` (hosted) and
   `App\Llm\OllamaAnalyst` (local) are bound in `AppServiceProvider`, selected by
   `ANALYST_PROVIDER`. Tests swap in `SpyAnalystLlm` — no real API call ever happens
   in tests, and assertions target the *retrieved evidence*, never generated prose.
2. **Retrieval is visibility-scoped.** Match retrievers run inside
   `GameMatch::visibleTo($user)`; trainings are limited to the caller's own teams
   (same scope as the trainings page). The model can only see — and therefore only
   cite — what the caller could open themselves. There is no path from another
   user's data into the prompt.
3. **Grounding is server-side.** The system prompt (in `AnthropicAnalyst`, not user
   input) requires: answer only from the evidence, cite matches as `[match:ID]`, admit
   gaps instead of inventing. The frontend turns citations into chips that open the
   match — every claim is checkable against the real dashboard.
4. **Bounded evidence.** 15 matches / 40 kill rows / 5 related cards / 10 trainings
   per request — the prompt size and the per-call cost are capped by construction, not
   by hoping questions stay small.
5. **Degrade, never 500.** No `ANTHROPIC_API_KEY`, provider outage, or Meilisearch down
   → `503 analyst.unavailable` (search-down only drops the kills evidence; the answer
   still comes from scoreboards). Codes, not sentences, as everywhere.
6. **Paid calls are throttled.** `throttle:10,1` on the route — an order of magnitude
   tighter than the generic limits, because every request is an LLM bill.
7. **Gated by `search.use`.** Same corpus as search, same ability — no new permission.

## API

| Method & path | Auth | Notes |
|---|---|---|
| POST `/api/analyst/ask` | sanctum + `can:search.use`, throttle 10/min | body `{question: string 5..500}` → `{data: {answer}}`; 503 `analyst.unavailable` when degraded |

Error codes: `analyst.question_required`, `analyst.question_too_short`,
`analyst.question_too_long`, `analyst.unavailable`.

## Config

`config/clutch.php` → `anthropic.key` / `anthropic.model` from `ANTHROPIC_API_KEY` /
`ANTHROPIC_MODEL` (repo-root `.env` via Compose; key empty by default = feature off).
The key is a secret: env-only, never committed — same rule as `DISCORD_WEBHOOK_URL`.

`analyst_provider` (`ANALYST_PROVIDER`, default `claude`) chooses the generator:
`claude` (needs `ANTHROPIC_API_KEY`, bills per call) or `ollama` (local container, free).
`clutch.ollama.*` configures the local one — `OLLAMA_CHAT_MODEL`, and `OLLAMA_TIMEOUT`
(default 600s, because CPU fallback is slow). The controller's availability check is
provider-aware: only the hosted provider is gated on a key, since an unreachable local
container already degrades through the `catch`.

The embedder (`EmbeddingClient`) and store (`SemanticRetriever`) are bound in the same
provider; `OllamaEmbeddings`/`HashEmbeddings` + `PgVectorRetriever`.

Postgres runs the `pgvector/pgvector:pg16` image (stock PG16 + the `vector` extension);
the migration `CREATE EXTENSION`s it. Tests use sqlite (no vector type) and fake the
retriever, so the suite never needs pgvector.

## Observability

One `analyst: answered` line per question (`AskAnalystAction`), carrying the question, the
four evidence counts, the answer's length, and the generation time. Nothing else records
that the analyst ran — the route logs only a status, and a slow or empty answer looks
exactly like a fast one from outside.

The counts are what make it diagnostic rather than decorative: `"kills":0` means
Meilisearch matched nothing and the answer stood on scoreboards and semantic recall alone
(the documented degrade path), and `seconds` is the number that jumps when the local model
falls back to CPU — 8.9s on GPU against ~265s on CPU for the same shape of question.

The answer's **text is deliberately not logged**. The question is enough to reproduce it,
and generated prose about the team's matches would otherwise sit in Loki for anyone with
Grafana access.

`LOG_CHANNEL=stderr` sends api logs to the container's stdout, where Alloy already scrapes
every service off the Docker socket — so this is queryable in Grafana with
`{service="api"} |= "analyst"`. (Logs written from `docker compose exec` sessions go to
that session's stderr, not the container's, so only real requests appear in Loki.)

## Deliberate limitations (honest tradeoffs)

- **The local generator is materially weaker.** `ANALYST_PROVIDER=ollama` costs nothing
  and keeps every question on the machine, but a 7B model holds the system prompt loosely:
  it obeys the citation and verbatim-name rules while ignoring the format ones (asked for
  a few sentences of plain text, it returns a bolded, numbered report). Treat local as the
  private/free option, not the good one — `ANALYST_PROVIDER` switches back to `claude`
  with no code change.
- **Local generation is usable on a GPU and unusable without one.** The same shape of
  question takes **~9s with the model on the GPU and ~265s on CPU** — a 30x swing, with no
  error and nothing in the response to distinguish them. Ollama falls back to CPU whenever
  the model doesn't fit in VRAM, which on a desktop card mostly consumed by the desktop is
  the normal case; only `ollama ps` reports it (`100% CPU`). The 600s timeout exists for
  the bad case, and the `seconds` field in the `analyst: answered` log is how you tell
  which one you got.
- **Crude embeddings, when the hash stand-in is selected.** `EMBED_PROVIDER=hash` captures
  word overlap with light stemming, not meaning — "duel" won't find "fight". It exists so
  the architecture runs with no external anything; `ollama` is the real embedder.
- **Match-level semantics only.** Cards summarize matches, so semantic search finds
  relevant *games*, not relevant *rounds*. Round-level meaning would need embedded
  round/kill cards — more vectors, same pattern.
- **No conversation memory.** Each question is independent; there is no chat history.
- **Recency window.** Evidence is the newest 15 matches — "last season" questions
  silently see only that window. The model is told to admit gaps, but the window
  itself isn't surfaced to the user yet.
