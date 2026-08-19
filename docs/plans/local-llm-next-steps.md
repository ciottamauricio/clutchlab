# Local LLM: next steps

Five follow-ups to the local-model work (`ollama` service, `OllamaEmbeddings`,
`OllamaAnalyst`). Ordered so each teaches roughly one new thing, cheapest first.

This is a **summary plan**: enough to decide with, not enough to build from. Each step
gets its own detailed plan when it's picked up.

## Where this starts

Already built and pushed:

- `ollama` Compose service + `ollama-init` (pulls the embedding model), GPU optional.
- `OllamaEmbeddings` behind `EmbeddingClient` — `nomic-embed-text`, 768 dims.
- `OllamaAnalyst` behind `AnalystLlm` — `qwen2.5-coder:7b`, selected by `ANALYST_PROVIDER`.

Two measurements worth keeping in view, because they shape every step below:

- A full analyst answer takes **~9s on the GPU and ~265s on CPU** — same question, 30x
  apart. Ollama falls back to CPU whenever the model doesn't fit in VRAM and reports it
  only in `ollama ps`, so "the analyst is slow today" is a VRAM question first.
- The 7B model **obeyed the citation rules and ignored the formatting ones**. Grounding
  discipline degrades with model size; it doesn't disappear.

---

## 1. Auto-embed on `match.parsed` — DONE

**Why:** `analyst:embed` is manual, so a newly parsed match is absent from semantic
search until someone remembers to run it. Nothing reports the gap — the analyst just
quietly answers from a stale index.

**Shape:** a new `EventHandler` on `match.parsed` that embeds that one match's card,
next to `ApplyMatchParsed`. Registration is one line in the `tag()` call in
`AppServiceProvider`.

**Teaches:** one event, many independent reactions — the payoff of publishers not
knowing their subscribers.

**Watch out for:**
- Ordering. This handler and `ApplyMatchParsed` both fire on `match.parsed`, and the card
  is built from the row that `ApplyMatchParsed` writes. Embedding first would embed a
  match that still says `queued`.
- Embedding calls Ollama over HTTP, inside the listener process. A slow or down container
  must not kill the listener or block other handlers.
- `analyst:embed` stays: it's the rebuild path, and the projection is disposable by design.

**Cost:** ~20 minutes. **Risk:** low.

**Built as `EmbedParsedMatch`.** The ordering concern was real: the card reads columns
`ApplyMatchParsed` writes, so embedding first stores "unknown map" with no score. It's
registered after that handler *and* re-checks the status, since array order is a
convenience rather than a guarantee.

The second half wasn't in the original sketch. The dispatch loop had no per-handler
try/catch, so one thrower skipped every handler after it — tolerable when all of them only
touched Postgres, but not once a handler calls ollama over HTTP. Handlers are now isolated
individually.

---

## 2. Stream the analyst answer

**Why:** four minutes of blank screen is unusable; four minutes of arriving tokens is
tolerable. This is what makes local generation *feel* viable rather than merely work.

**Shape:** Ollama supports `"stream": true`, emitting newline-delimited JSON chunks.
Getting those to the browser is the real design question, and there are two credible
answers:

- **Through Laravel** (SSE from the api). Keeps one path for the analyst; means a PHP
  process held open for minutes.
- **Through `realtime`** (the existing Go websocket service). Go is built for held-open
  connections, and `realtime` already owns the websocket boundary — but now two services
  talk to Ollama, and the api still owns retrieval.

**Teaches:** the most interesting boundary question in the project — streaming across a
service split, and where a long-lived connection belongs in a polyglot stack.

**Watch out for:**
- `AnalystLlm::answer()` returns `string`. Streaming doesn't fit that signature. Whether
  to add a second method, return an iterator, or leave the contract alone and stream
  outside it **is the design decision**, not an obstacle to it.
- Retrieval (~seconds) precedes generation (~minutes). The user should see that split.
- Errors mid-stream can't become a 503 — headers are already sent.

**Cost:** ~2-3 hours. **Risk:** medium; touches api, `realtime`, frontend.

---

## 3. Round-level embeddings — DONE

**Why:** `analyst.md` lists this under deliberate limitations — cards summarize *matches*,
so semantic search finds relevant games, never relevant rounds. "Rounds where we lost a
5v3" is unanswerable today.

**Shape:** a second projection of round cards, built from `analytics.round_events` +
`kill_events`, retrieved alongside match cards.

**Teaches:** least new architecture — this is the pattern already built, scaled. It is
the largest *answer-quality* gain on the list.

**Watch out for:**
- `SemanticRetriever` is match-shaped (`related()` → `match_id`, `index(int $matchId, …)`).
  Rounds need either a second contract or a generalized one. **Deciding which is the
  substance of this step.**
- Corpus grows ~20x (26 matches → ~500+ rounds). `analyst.md` documents "no ANN index on
  purpose" for a tiny corpus; this is plausibly where a sequential scan stops being
  instant — the "earned upgrade" the doc anticipates. Measure before adding ivfflat.
- Embedding ~500 cards through a local model is minutes, not seconds. Batch it.
- More retrieved evidence = a bigger prompt = slower generation, which step 2 makes
  visible rather than fixing.

**Cost:** ~2 hours. **Risk:** medium — mostly the contract decision.

**Built as `RoundRetriever` + `round_embeddings`.** The contract went to a separate
interface rather than a generalized `corpus` parameter: both have one implementation
today, and the third corpus (docs, step 4) scopes by nothing at all, so the "shared"
abstraction would have fitted neither.

Two things the sketch missed. `forget()` has to clear a match's rounds before re-indexing,
because a re-parse can yield fewer rounds than the last and per-round upserts leave the
surplus behind. And the card's *vocabulary* is the actual work — a round is only findable
by words some card says, so `bomb_defused` had to become "defusing the bomb" and a
1-survivor win "a clutch".

The scan-cost worry didn't materialize: 611 rounds, exact sequential scan, **2.15ms**. No
ivfflat. Re-measure again if kill-level cards ever land (~30x more).

---

## 4. RAG over the project's own docs — DONE

**Why:** `ARCHITECTURE.md`, four `CLAUDE.md` files and nine domain docs are a corpus of
*design rationale*. It answers "why is the parse queue a plain Redis list?" — a question
grep cannot.

**Shape:** a second corpus and vector table, chunked by markdown heading (the docs are
already structured that way), reusing `EmbeddingClient` unchanged.

**Teaches:** whether the embedder/retriever seams genuinely generalize or only appeared
to. That's a real test of the abstraction, and the same mechanics as any
"RAG over a document set" tool — including the security-notes workflow from the class.

**Watch out for:**
- Chunking is the whole game. Too big and retrieval returns noise; too small and it
  returns fragments with no context.
- This corpus has **no `match_id` and no per-user visibility**, so `SemanticRetriever`'s
  contract doesn't fit at all — unlike step 3, where it merely bends.
- Honest limit: you wrote these docs. The lesson is architectural, not daily utility.

**Cost:** ~3 hours. **Risk:** low, but least payoff per hour.

**Built as `DocRetriever` + `doc_embeddings`, 224 chunks over 29 files.** It stopped short
of `AskAnalystAction` on purpose, and it still does — letting an untuned corpus into every
answer is how you make the analyst worse without noticing.

**Since finished as its own endpoint** (`POST /docs/ask` → `AskDocsAction` + `DocsLlm`),
surfaced as an ask-box on the engineering study page. Not a flag on the analyst: the two
corpora scope by different things and cite different things, so they got separate contracts
and separate prompts. Full write-up in `api/docs/domains/analyst.md`.

The wiring taught one thing the retrieval work hadn't, and it was not a prompt lesson.
**Answer quality collapsed on evidence VOLUME, not instructions.** Six excerpts (~8.5KB) and
the 7B model abandoned the corpus for a generic essay about Redis; four (~4KB) and it gave
the project's actual reason — identical prompt, identical retrieval, identical top score.
Two prompt rewrites moved nothing. The related discovery: `[doc:path#heading]` citations
never appeared at all, at any size, and numbered `[1]` markers appeared immediately — so
the number-to-path mapping happens server-side. Both belong to step 5's risk profile too:
the failures were invisible from the retrieval scores, which looked good throughout.

**The abstraction question got its answer, and it was "stay separate".** This corpus scopes
by nothing — no `match_id`, no per-user visibility — so `related()` takes no scope argument
where both siblings take `$matchIds`. The seam that actually generalized across all three
is `EmbeddingClient`, which never changed.

Two things the sketch didn't anticipate. **The corpus is defined by its exclusions**: 20
files of vendored Terraform docs outweigh the hand-written ones 3:2, and Laravel's stock
`api/README.md` put its *Code of Conduct* in the top 3 for a question about error codes
until scaffolding was filtered by content. And the api container mounts only `./api`, so
the repo root needed a read-only bind mount before a docs corpus could exist at all — the
boundary being the point of the project, this one was worth the extra line in Compose.

**The measured ceiling, which is also the argument against step 5.** Conceptual questions
retrieve well ("why Go instead of PHP" → 0.682, right section first). Questions naming a
*symbol* do not ("what does `DocRetriever` do" → 0.523, misses a section that answers it
directly). The embedder knows prose, and an identifier is not a word to it. Step 5 is RAG
over code, where **every** question names a symbol — so this step's result is evidence its
ranking at the bottom of this list was right.

**Two unrelated things fixed on the way** (both were quietly costing more than they looked):

- `OllamaEmbeddings` had a hardcoded 30s timeout and no retry. On a desktop GPU the model
  is evicted mid-batch by whatever else holds VRAM — on WSL2, the Windows desktop's usage
  counts against the same 8GB and is invisible from Linux — and a reload under pressure
  looks exactly like a hang. Now configurable (120s default) with two backed-off retries.
- The whole test suite reported **warnings instead of passes** — 89 of them — because
  Laravel's env bootstrapper read a `.env` this service deliberately doesn't have.
  `APP_ENV` needed a `<server>` override in `phpunit.xml` for the same reason the DB
  settings already had one: Compose's `env_file` lands in `$_SERVER`, which is read before
  PHPUnit's `<env>` applies. 112 tests had been passing silently behind that warning.

---

## 5. Security review over the codebase

**Why:** the bug-bounty workflow from the class — embed the code, ask where user input
reaches a sink.

**Shape:** same as step 4 with a different corpus and prompt, chunked by class or method.

**Ranked last, deliberately.** A 7B model produces confident false positives on real code,
and verifying them costs more than the tool saves. Treat this as a study of *why*
RAG-over-code is hard — chunking loses call-graph context, and a model that can't follow a
value across files can't tell a sanitized path from an unsanitized one. Your Laravel
conventions (Form Requests validating at the boundary, actions never trusting input) are a
good test set precisely because a naive reviewer flags them constantly.

**Watch out for:**
- Chunking code by class breaks exactly the cross-file flow that matters.
- No ground truth. Without known-bad samples you can't tell a good run from a bad one —
  seeding a deliberate flaw is the only honest way to measure.

**Cost:** ~4 hours. **Risk:** low technically, high in wasted effort if treated as a
real scanner.

---

## Order

**1 → 2 → 3** is the recommended path: fix staleness, make local generation usable, then
make answers better. 4 and 5 are optional side-quests that reuse the machinery rather
than extending it.

Steps 1-3 all touch the analyst, so each should land with `analyst.md` updated in the same
commit — the domain-doc rule in `api/CLAUDE.md`.
