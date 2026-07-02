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
├── App.jsx                 # composition root (a router goes here once there's a 2nd route)
├── lib/                    # cross-cutting, domain-agnostic
│   ├── api.js              # fetch wrapper: base URL + error-code handling
│   └── i18n.js             # code → text (placeholder for react-i18next — see §4)
├── components/             # PRIMITIVES — reusable, know NOTHING about CS2
│   └── (none yet — extracted from real repetition; see §5)
├── features/               # DOMAIN — one folder per domain, mirrors the backend
│   └── matches/
│       ├── api.js          # useMatches(), useMatch(), useUploadDemo() — data hooks
│       ├── UploadDemo.jsx
│       ├── MatchList.jsx
│       ├── MatchDashboard.jsx
│       ├── Scoreboard.jsx
│       └── StatusBadge.jsx
│   # tactics/, teams/, auth/ arrive as those domains are built
└── pages/                  # ROUTES — thin, just compose features
    └── DashboardPage.jsx
```

Everything about a domain lives in one folder. Delete/rework = one folder. Claude Code
works on one domain = reads one folder. (`components/` is empty today on purpose — see §5;
`locales/en.json` + `pt-BR.json` arrive with react-i18next.)

---

## 2. Three layers + downward-only dependencies

| Layer | Lives in | Knows about domain? | Reuse profile |
|-------|----------|---------------------|---------------|
| **Primitives** | `components/` | No | Drop into any app unchanged |
| **Domain components** | `features/` | Yes | Reusable within Clutchlab only |
| **Pages** | `pages/` | Yes | Not reused; thin composition |

**Dependencies flow downward only:** pages → features → primitives → nothing.
A `Button` importing `MatchCard` is the smell that something's in the wrong layer.
Target: enforce with an ESLint import rule so it's automatic, not just discipline
(the scaffold currently ships `oxlint`; the import-boundary rule isn't wired up yet).

- Primitive test: *could I drop this into a totally different app unchanged?* → `components/`
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

Abstractions built from one imagined example are almost always wrong. *This is why
`components/` is empty right now* — the matches feature was built with its own components;
primitives get extracted only when a second feature proves what actually repeats.

**Composition over configuration:**
- Good: `<Card><Card.Header>…</Card.Header></Card>` — caller arranges via children.
- Avoid: `<Card title= subtitle= footer= action= />` — a prop per slot, hits a wall fast.

**Keep primitives dumb.** The more a component knows, the less reusable it is. A `Button`
that decides *what* it submits is coupled forever. Pass behavior in; let it stay ignorant.

---

## Build order

The **matches** feature was built first with plain elements and no component library —
everything inline in `features/matches/`. As the second/third features land (tactics, teams)
you'll see which primitives actually repeat, and extract `components/` from real evidence.
Starting with a big design system is the frontend version of starting fully-microservices on
day one.
