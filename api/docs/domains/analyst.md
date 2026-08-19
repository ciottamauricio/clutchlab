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
         │             pgvector `round_embeddings`: 3 nearest ROUND cards, same scope —
         │             which round, not just which game (degrades to [] if down)
         │             Postgres: the caller's teams' 10 most recent trainings —
         │             tactics drilled, roster + RSVPs, nade homework
         ├──▶ AUGMENT  compact JSON evidence + the question in one user message
         └──▶ GENERATE one Claude call (AnalystLlm), grounded by the system prompt
```

Matches and kills are **outcomes**; trainings are **intent**. The system prompt names
the split, so the analyst can connect "what we practiced" to "what happened" —
a question no single page in the app can answer. Trainings need no keyword
retrieval: the corpus is small and recency IS relevance for practice questions.

### Two grains of semantic recall (match and round)

Match cards answer *which game*; they cannot answer *which round*, because a whole match
compressed into one vector has no room for one round's shape. `round_embeddings` is the
same projection one level down — one card per round, retrieved by `RoundRetriever` and
handed over as `semantically_related_rounds` (3 per question, against the matches' 5:
round cards are the narrowest evidence, and every card lengthens the prompt that
generation is slow over).

**The card is the retrieval surface.** `BuildRoundCardAction` writes prose, not columns,
because a round is only findable by words some card actually says: `bomb_defused` becomes
"defusing the bomb", a 1-survivor win becomes "a clutch", and an eco beating a full buy
becomes "an upset". Those phrasings are why "eco rounds where we upset a full buy" scores
0.750 against the right rounds. Adding vocabulary to the card adds questions the analyst
can answer — and inventing drama for ordinary rounds would spend that vocabulary on
nothing, so only the genuinely notable shapes get named.

**A deliberately separate contract.** `RoundRetriever` is its own interface rather than a
`corpus` parameter on `SemanticRetriever`. Both have one pgvector implementation today,
and a shared abstraction invented for two similar cases tends to fit neither once a third
arrives — embedded docs, for instance, have no match to scope by at all. Merge them when a
real third corpus shows what they actually share.

**`forget()` before re-indexing.** A re-parse can produce fewer rounds than the previous
one; per-round upserts alone would leave the surplus behind as cards for rounds that no
longer exist. Match cards need no such thing — one match is always exactly one card.

**Scan cost, re-measured.** 611 rounds against 25 matches is ~24x the corpus, which is the
scale the "no ANN index" note said to re-check. An exact sequential scan is **2.15ms**, so
the decision stands: still no ivfflat. Embedding all 611 cards takes ~14s on the GPU.

### A third corpus: the project's own documentation

`doc_embeddings` holds this repository's markdown, chunked by heading and retrieved by
`DocRetriever`. It answers *design rationale* questions — "why is the parse queue a plain
Redis list?" — that grep cannot, because the answer is an argument rather than a string.

**Built, but not yet wired into `AskAnalystAction`.** `docs:embed` and the retriever exist
and are verified standalone; no `semantically_related_docs` key reaches the prompt. A
corpus that answers questions about the codebase is easy to make *worse* than nothing by
letting it into every answer before its retrieval quality is known.

**The contract question, finally settled.** `RoundRetriever` was kept separate from
`SemanticRetriever` on the bet that a third corpus would show what they actually shared.
It did, by not fitting either: both siblings scope results to a caller's visible matches,
and a design doc belongs to no match and no user. `DocRetriever::related()` therefore takes
no scope argument at all. A generalized `corpus` parameter would have carried a `$matchIds`
argument that is meaningless here. **The seam that genuinely generalized is
`EmbeddingClient`** — the one that never had to change.

**What the corpus excludes is load-bearing.** Vendored dependency docs (a Terraform module
ships ~5,000 lines of upstream README and CHANGELOG) outweigh the hand-written docs roughly
3:2. Framework scaffolding is the subtler case: `api/README.md` is Laravel's stock readme,
and before it was excluded, "how do error codes reach the user interface?" retrieved
**Laravel's Code of Conduct** in the top 3. Scaffolding is matched on content, not path,
because the giveaway text is stable while filenames are not.

**Chunking is the whole game, and the docs were already written for it.** Sections are
argument-per-heading and top out around 46 lines, so `##`/`###` boundaries need no fallback
splitter. Two things a naive split gets wrong: headings inside fenced code blocks are shell
comments, not structure; and a chunk reading "merge them when a third corpus arrives" is
useless alone because it never says what "them" is — so every chunk is prefixed with its
file and heading ancestry, which is both the retrieval surface and what makes a hit
readable. Headings with no prose under them are dropped: a table-of-contents entry embeds
to a vector that can only ever match its own title.

**Scan cost.** 224 chunks scan exactly in **1.21ms** — smaller than the round corpus, so
for the third time there is no ivfflat.

**It retrieves concepts, not identifiers — measured.** "Why is the worker written in Go
instead of PHP?" scores **0.682** on the right section. "What does `DocRetriever` do?"
scores **0.523** and misses, even though a section explaining exactly that exists. The
embedder is trained on prose, and a class name is a token it has no meaning for; the
`bomb_defused` → "defusing the bomb" lesson from round cards applies to *queries* too, and
here the query is the half we don't control. This is the honest ceiling on RAG-over-docs:
it answers the questions a newcomer asks in English, not the ones you ask with a symbol in
hand — which is what step 5 (RAG over *code*) would need and a reason to expect less of it.

**Staleness is manual here, by design.** The other two indexes are projections of Postgres
and rebuild from `match.parsed`. This one projects the *working tree*, and nothing
publishes a "docs changed" event — so `docs:embed` is run by hand, and the index is stale
between runs.

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
`analyst:embed` remains the bulk rebuild path, for an embedder swap or a backfill. It
rebuilds match **and** round cards together; `docs:embed` is the separate command for the
documentation corpus, which projects files rather than Postgres and so has no event to
follow (`--dry` lists what would be chunked without calling the embedder).

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
| POST `/api/docs/ask` | sanctum only, throttle 10/min | body `{question: string 5..500}` → `{data: {answer, sources[]}}`; 503 `docs.unavailable` / `docs.not_indexed` |

Error codes: `analyst.question_required`, `analyst.question_too_short`,
`analyst.question_too_long`, `analyst.unavailable`; `docs.question_required`,
`docs.question_too_short`, `docs.question_too_long`, `docs.unavailable`,
`docs.not_indexed`.

### Why `/docs/ask` is gated by authentication alone

Every other retrieval route sits behind an ability because it reads *the caller's* data,
and the ability names which slice. This corpus is the repository's own markdown: identical
for every caller, scoped by nothing, and already readable by anyone who can read the repo.
There is no slice to name. Authentication still applies, because each call spends local GPU
time — the boundary being protected is the machine, not the content.

## The docs loop (`POST /docs/ask`)

A second, shorter RAG loop over `doc_embeddings`: retrieve the nearest chunks of the
project's own markdown → generate. It shares `EmbeddingClient` with the analyst and shares
nothing else — separate action (`AskDocsAction`), separate contract (`DocsLlm`), separate
prompt, separate endpoint. `AnalystLlm` mandates `[match:N]` citations, which a design
document cannot satisfy; asking a small model to cite ids absent from its evidence is an
invitation to invent them.

Two numbers in this loop were measured rather than chosen, and both contradict the
intuition that a better prompt is the lever:

- **`CHUNK_LIMIT = 4`.** At six excerpts (~8.5KB) the 7B model stopped answering from the
  corpus and produced a fluent, generic essay about Redis — seven advantages, none of them
  this project's reason. At four (~4KB) the same question, same retrieval and same top
  score (0.684) produced the actual reason: Laravel's queue serializes PHP job objects Go
  cannot read. Rewriting the prompt twice changed nothing; cutting the evidence changed
  everything. **More retrieval is not more grounding** — past some volume a small model
  treats the excerpts as background and falls back on what it already knows.
- **Numbered citations.** `[doc:path#heading]` produced *zero* citations at every evidence
  size; a 7B model will not reproduce a long punctuated path mid-sentence. Numbered
  excerpts cited as `[1]` worked immediately, and `AskDocsAction::resolveCitations()`
  expands them back into paths — so the model's limitation stays server-side and the
  frontend still sees stable `[doc:…]` markers. A number with no matching excerpt is
  dropped rather than rendered: that is an invented citation.

`sources[]` carries `path`, `heading` and `similarity` but never the chunk text — the text
is already the prompt, and echoing it would send the page kilobytes of markdown it never
renders. The score is returned because it is the honest part: it separates "the docs
answered this" from "the model improvised over weak matches", and the study page shows it.

**Known ceiling.** Conceptual questions retrieve well (~0.63–0.76). Questions naming a
*symbol* do not — "what does `DocRetriever` do" tops out at ~0.52 and misses sections that
answer it directly. The embedder knows prose; an identifier is not a word to it. The study
page deliberately keeps one such question among its suggestions.

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
- **The docs corpus is never consulted BY THE ANALYST.** `doc_embeddings` has its own
  endpoint now (`POST /docs/ask`, see below), but no `semantically_related_docs` is in the
  analyst's evidence payload and none is planned: a corpus answering "why is it built this
  way?" would compete for prompt space with the evidence answering "what happened in our
  matches?", and the second is what the analyst is for. The two loops stay apart.
- **The docs index goes stale silently.** It projects the working tree, not Postgres, and
  nothing publishes a "docs changed" event. Edit a doc and the index describes the old one
  until `docs:embed` runs again. The match and round indexes don't have this problem —
  `match.parsed` keeps them current.
- **Round-level semantics stop at the round.** Rounds are embedded (see below), but kills
  are not: a question about a specific duel still resolves only to the round it happened
  in. Kill cards would be the next grain down — ~30x again, and the point where the
  no-ANN-index decision would finally need revisiting.
- **No conversation memory.** Each question is independent; there is no chat history.
- **Recency window.** Evidence is the newest 15 matches — "last season" questions
  silently see only that window. The model is told to admit gaps, but the window
  itself isn't surfaced to the user yet.
