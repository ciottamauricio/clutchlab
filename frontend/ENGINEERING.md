# Frontend Engineering — Clutchlab

Conventions for the React frontend. Same philosophy as the backend: **find the seam,
don't over-abstract, feel the pain before adding structure.**

App-wide conventions live in [`../docs/ENGINEERING.md`](../docs/ENGINEERING.md); this is
the frontend deep-dive. **This doc intentionally supersedes the `src/hooks/` + `src/components/`
rule from the global conventions for this project** — Clutchlab uses feature folders (below).

---

## 1. Structure: feature folders, not type folders

Group by *what a file is about*, not *what a file is*. Features mirror the backend
domains in `api/docs/domains/`.

```
frontend/src/
├── main.jsx
├── App.jsx                 # composition root: router + route guards
├── lib/                    # cross-cutting, domain-agnostic
│   ├── api.js              # fetch wrapper: base URL, auth token, error codes
│   ├── i18n.js             # code → text (placeholder for react-i18next — see §4)
│   └── theme.js            # light/dark context
├── components/             # PRIMITIVES — reusable, know NOTHING about CS2
│   ├── SearchableSelect.jsx
│   └── ThemeToggle.jsx
├── layout/                 # APP CHROME — the shell around every route (see §2)
│   ├── AppLayout.jsx       # app bar, nav, <Outlet/>
│   └── UserMenu.jsx
├── features/               # DOMAIN — one folder per domain, mirrors the backend
│   ├── shared/             # domain code with no single owning feature
│   │   └── BodyHitgroups.jsx
│   ├── matches/
│   │   ├── api.js          # useMatches(), useMatch(), useUploadDemo() — data hooks
│   │   ├── format.js
│   │   ├── UploadDemo.jsx
│   │   ├── MatchList.jsx
│   │   ├── MatchDashboard.jsx
│   │   └── …
│   └── auth/ teams/ tactics/ search/ trainings/ analyst/ dora/ …
└── pages/                  # ROUTES — thin, just compose features
    └── DashboardPage.jsx, TeamsPage.jsx, …
```

Everything about a domain lives in one folder. Delete/rework = one folder. Claude Code
works on one domain = reads one folder. The payoff is measurable: across thirteen features
there is **not one cross-feature import**. (`locales/en.json` + `pt-BR.json` arrive with
react-i18next.)

`features/shared/` is the release valve for domain code that several features need and none
owns — `BodyHitgroups` renders a CS2 silhouette for six call sites in five features. It is
*not* a primitive (it knows what a hitgroup is), so it can't live in `components/`; parking
it in `features/matches/` would force the other four to import across a domain boundary,
which is the one thing this structure exists to prevent.

---

## 2. Four layers + downward-only dependencies

| Layer | Lives in | Knows about domain? | Reuse profile |
|-------|----------|---------------------|---------------|
| **Primitives** | `components/` | No | Drop into any app unchanged |
| **Domain components** | `features/` | Yes | Reusable within Clutchlab only |
| **App chrome** | `layout/` | Yes — session + routes | Never reused; there is one shell |
| **Pages** | `pages/` | Yes | Not reused; thin composition |

**Dependencies flow downward only:** pages → features → primitives → nothing.
A `Button` importing `MatchCard` is the smell that something's in the wrong layer.

**Chrome is the one sanctioned exception.** `layout/AppLayout` imports `features/auth` —
it reads the session to decide which nav links exist, and `UserMenu` needs the current user
to log out. That looks like an upward import, and by the primitive rule it would be one.
It isn't a violation, because chrome is not a primitive: session and navigation are
*what chrome is*. The rule that survives is narrower and enforceable — **`components/` may
import nothing but React and `lib/`**; `layout/` may additionally import `features/auth`.

This layer was added after the fact. For a long time `AppLayout` and `UserMenu` sat in
`components/` alongside the real primitives, quietly contradicting the paragraph above, and
that contradiction is exactly why the ESLint rule below never got written: no rule could be
true of that folder. Naming the fourth layer is what makes the rule expressible.

Target: enforce with an ESLint import rule so it's automatic, not just discipline
(the scaffold currently ships `oxlint`; the import-boundary rule isn't wired up yet).

- Primitive test: *could I drop this into a totally different app unchanged?* → `components/`
- Fails that test but no single feature owns it? → `features/shared/` (see §1)
- Long page file → you're missing a feature component.

---

## 3. Separate data-fetching from presentation

Components render; they don't fetch. Data logic goes in each feature's `api.js` as hooks.

Server state vs UI state are different things:
- **Server state** (matches, teams — lives in the backend) → feature hooks
- **UI state** (modal open? which match is selected?) → `useState` / context

Mixing them is the most common frontend architecture mistake.

**Now:** plain `useState`/`useEffect` hooks (what Step 1 shipped). **Target:** TanStack Query
(React Query) for server data once caching / dedup / invalidation earn the dependency — not a
global store. Adopt it when the pain is real, not before.

The parse-status poll, as currently implemented — note it stops on a **terminal** status
(`parsed` or `failed`), not a made-up `done`:

```jsx
// features/matches/api.js (implemented)
const TERMINAL = new Set(['parsed', 'failed'])

export function useMatch(id, pollMs = 2000) {
  // useEffect calls getMatch(id) and reschedules itself with setTimeout
  // until data.status is in TERMINAL, then stops.
}
```

The same behavior once we move to React Query — `refetchInterval` returns `false` to stop:

```jsx
useQuery({
  queryKey: ['match', id],
  queryFn: () => getMatch(id),
  refetchInterval: (q) =>
    ['parsed', 'failed'].includes(q.state.data?.status) ? false : 2000,
})
```

---

## 4. One API client + the error-code seam

The backend returns **codes, not sentences** (see `docs/ARCHITECTURE.md` i18n section).
Exactly one place handles the transport + code extraction: `lib/api.js`. Base URL is `/api`
so nginx routes it.

```js
// lib/api.js (implemented)
async function request(path, opts) {
  const res = await fetch(`/api${path}`, opts)
  const body = await res.json().catch(() => null)
  if (!res.ok) {
    // Laravel surfaces validation as { message, errors: { demo: [code] } }.
    const code = body?.errors?.demo?.[0] ?? body?.message ?? body?.error ?? 'error.unknown'
    throw new ApiError(code, res.status) // e.g. code = "demo.file_too_large"
  }
  return body?.data
}
```

A component turns the code into localized text via `t(err.code)` from `lib/i18n.js`. i18n is
**deferred** (`docs/ARCHITECTURE.md`), so `lib/i18n.js` is a plain code→text map today; it
becomes react-i18next backed by `locales/en.json` / `pt-BR.json` later, with the same `t()`
signature. No component hardcodes a message in either language.

---

## 5. Reusability discipline (the important part)

**Don't build for reuse before you have reuse.** A 40-prop `<Table>` before the second
table is the frontend version of cargo-culting microservices.

**Rule of three:**
1. First use → write it inline.
2. Second use → copy-paste it (yes, really).
3. Third use → now you understand the real variation → extract the reusable component.

Abstractions built from one imagined example are almost always wrong. `components/` stayed
empty for the whole of the matches feature for that reason: primitives get extracted only
when a second feature proves what actually repeats.

**What the rule actually produced.** Thirteen features later the folder holds two files, and
both earned it by repetition rather than by anticipation:

| Extracted | Call sites | Verdict |
|---|---|---|
| `BodyHitgroups` | 6, across 5 features | Real repetition — but domain-aware, so it lives in `features/shared/`, not here |
| `SearchableSelect` | 2 features | Extracted at two, not three — a 125-line portal/typeahead is worth not writing twice |
| `ThemeToggle` | 2 pages | Genuine primitive |

Two things worth keeping honest about that table. `SearchableSelect` broke the rule of three
and was still right: the rule is a heuristic against *speculative* abstraction, and a
component with keyboard nav and portal positioning has enough irreducible complexity that
the second copy already hurts. And the biggest piece of shared UI in the app is not a
primitive at all — which is the finding, not an exception. Repetition tells you *that*
something should be extracted; it does not tell you *which layer it belongs in*.

**Composition over configuration:**
- Good: `<Card><Card.Header>…</Card.Header></Card>` — caller arranges via children.
- Avoid: `<Card title= subtitle= footer= action= />` — a prop per slot, hits a wall fast.

**Keep primitives dumb.** The more a component knows, the less reusable it is. A `Button`
that decides *what* it submits is coupled forever. Pass behavior in; let it stay ignorant.

---

## Build order

The **matches** feature was built first with plain elements and no component library —
everything inline in `features/matches/`. Primitives were extracted only once later features
proved what actually repeats. Starting with a big design system is the frontend version of
starting fully-microservices on day one.

That worked: thirteen features in, the shared surface is five files totalling under 300
lines, and there is still no component library. What it did *not* do on its own was keep the
layers straight — `components/` accumulated app chrome and a domain component before anyone
noticed the folder no longer matched its own definition (§2). Deferring abstraction avoids
the wrong component; it does not tell you where the right one goes.
