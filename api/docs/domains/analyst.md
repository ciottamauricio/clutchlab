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
         │             Meilisearch `kills`: 40 hits matching the question's words,
         │             scoped to those same matches (degrades to [] if down)
         ├──▶ AUGMENT  compact JSON evidence + the question in one user message
         └──▶ GENERATE one Claude call (AnalystLlm), grounded by the system prompt
```

## Rules

1. **Generation sits behind an interface.** `App\Contracts\AnalystLlm` (one method:
   `answer(question, evidence)`); `App\Llm\AnthropicAnalyst` is bound in
   `AppServiceProvider`. Tests swap in `SpyAnalystLlm` — no real API call ever happens
   in tests, and assertions target the *retrieved evidence*, never generated prose.
2. **Retrieval is visibility-scoped.** Both retrievers run inside
   `GameMatch::visibleTo($user)` — the model can only see (and therefore only cite)
   matches the caller could open themselves. There is no path from another user's data
   into the prompt.
3. **Grounding is server-side.** The system prompt (in `AnthropicAnalyst`, not user
   input) requires: answer only from the evidence, cite matches as `[match:ID]`, admit
   gaps instead of inventing. The frontend turns citations into chips that open the
   match — every claim is checkable against the real dashboard.
4. **Bounded evidence.** 15 matches / 40 kill rows per request — the prompt size and
   the per-call cost are capped by construction, not by hoping questions stay small.
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

## Deliberate limitations (honest tradeoffs)

- **Keyword retrieval, not embeddings.** Meilisearch matches the question's words
  against killer/victim/weapon/map. Precise for structured data; blind to paraphrase
  ("long-range duels" won't find AWP kills). A vector store + embeddings would fix
  that — the earned upgrade, same logic as list→Streams for the queue.
- **No conversation memory.** Each question is independent; there is no chat history.
- **Recency window.** Evidence is the newest 15 matches — "last season" questions
  silently see only that window. The model is told to admit gaps, but the window
  itself isn't surfaced to the user yet.
