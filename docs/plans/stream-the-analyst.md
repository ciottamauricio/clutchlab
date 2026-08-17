# Streaming the analyst answer

Step 2 of [`local-llm-next-steps.md`](local-llm-next-steps.md). Turn the analyst from
"blank panel, then a wall of text" into tokens arriving as they're generated.

## Why

Generation takes ~9s on GPU and ~265s on CPU (topic 22). Neither is fixable, but both are
survivable if the user can see it working. A wait with visible progress is a different
experience from the same wait with none, and the only honest fix at this hardware tier is
to show the work rather than shorten it.

Retrieval (~tens of ms) precedes generation (seconds to minutes). That split should be
visible too: "found 15 matches, 40 kills…" lands almost immediately.

## The decision: who holds the connection

The plan sketch left this open. Reading the code closes it.

**Laravel SSE is not viable here.** The api runs `php artisan serve` — PHP's built-in
server — with `PHP_CLI_SERVER_WORKERS` unset, so it handles **one request at a time**. A
held-open SSE connection would block *every other api request* for its entire duration: 9
seconds of a frozen app on a good day, 4 minutes on a bad one. That isn't a tuning
problem, it's the dev server's design.

Options considered and rejected:

- **Set `PHP_CLI_SERVER_WORKERS=4`** — makes the block less total, not absent, and papers
  over a dev-server limitation with a dev-server flag.
- **Move the api to php-fpm + nginx** — the real fix for production, and a much larger
  change than this step. Worth its own step someday; not the price of streaming.

**So: `realtime` holds the connection.** It already exists for exactly this shape of work —
long-lived connections, cheap goroutines — and it already authenticates Sanctum tokens
straight from Postgres (`store.UserIDForToken`), so there's no new auth story. nginx
already proxies `/realtime/` with `proxy_read_timeout 3600s`, which is what a slow
generation needs.

This is the project's own thesis applied: the boundary exists because the workloads
differ. Batch parsing went to the worker; held-open connections go to `realtime`.

## The shape

```
browser ──ws──▶ realtime ──http──▶ api  (POST /api/analyst/evidence, internal)
                    │                     retrieval only, returns evidence JSON
                    └────http──▶ ollama  (/api/chat, stream: true)
                         ◀── tokens ──┘
browser ◀──ws── realtime  (forwards each token, then a done frame)
```

**The api keeps owning retrieval**, and that matters. Every retriever is visibility-scoped
(`GameMatch::visibleTo`), the trainings query is team-scoped, and those rules are Laravel's
business logic. Reimplementing them in Go would duplicate the security boundary in a second
language — the exact mistake the DB split was made to avoid.

`realtime` does only what it's good at: hold the socket, call ollama, forward bytes.

## Open questions to settle while building

**1. Does `AnalystLlm` change?** The contract returns `string`. Three options:

- Leave it alone; streaming lives entirely in Go and the PHP contract stays the
  non-streaming path. *Simplest, and the two paths can drift.*
- Add `answerStreaming(): iterable` alongside. *Honest, but only one implementation would
  ever use it — Go does the streaming.*
- **Leaning:** leave it alone. The contract describes what the *api* does, and after this
  change the api doesn't generate for the streaming path at all. Forcing a Go-side concern
  into a PHP interface would be abstraction for its own sake.

**2. How does `realtime` authenticate the internal call to the api?** It needs evidence for
a user it has already authenticated. Either forward the caller's Sanctum token, or add an
internal-only route. Forwarding the token is simpler and keeps the api the single authority
on what that user may see.

**3. What does the frontend do with a `503`-equivalent?** Errors mid-stream can't be an
HTTP status — headers are long since sent. Needs an explicit error frame, and
`useAnalyst` must map it to the same `analyst.unavailable` code the non-streaming path
returns, or the frontend grows two error vocabularies.

**4. Does the non-streaming endpoint stay?** Yes. It's what the tests exercise, it's the
Claude path (`ANALYST_PROVIDER=claude` has no reason to stream through Go), and it's the
fallback when the websocket won't open.

## Steps

1. **`POST /api/analyst/evidence`** — retrieval only, returns the evidence JSON that
   `AskAnalystAction` builds today. Extract the retrieval half of that action so both
   endpoints share it; no logic moves to Go.
2. **`realtime`: `/realtime/analyst`** — authenticate the token, fetch evidence from the
   api, call ollama with `stream: true`, forward each chunk as a ws frame, then `done`.
3. **Frontend:** `useAnalyst` gains a streaming path — append tokens to `answer` as they
   arrive, keep the existing citation-chip rendering (it already re-parses on each render,
   so partial text works).
4. **Fallback:** if the websocket fails to open, fall back to `POST /analyst/ask`.
5. **Docs:** `analyst.md` (the loop now has two shapes), `notifications.md` is untouched,
   and topic 22 or a new topic for the streaming boundary.

## Risks

- **Two paths to one feature.** Streaming and non-streaming can drift. Mitigated by both
  sharing retrieval and the same grounding prompt — but the prompt would then live in two
  languages, which is a genuine cost worth naming rather than hiding.
- **`realtime` gains a second reason to exist.** It was the tactics service; it becomes the
  "held-open connections" service. That's a coherent identity, but it's a re-framing.
- **Partial answers are unciteable.** `[match:` may arrive split across chunks. The chip
  renderer must tolerate a half-written citation without flashing broken output.
- **No test covers a stream end-to-end.** Go tests can fake ollama; the browser path would
  be manual until there's a frontend test setup (none exists today).

## Not in scope

Moving the api to php-fpm, streaming the Claude path, and cancelling an in-flight
generation (the user closing the panel should ideally stop the model; today it wouldn't).
