# Clutchlab frontend (React + Vite)

The UI: upload demos, list matches, show the parsed dashboard. It reaches the API only
through nginx at `/api` (never a service hostname).

**Read [`ENGINEERING.md`](ENGINEERING.md) before building here** — it is the source of
truth for frontend structure and conventions, and it deliberately supersedes the
`src/hooks/` + `src/components/` rule from the global conventions.

## The essentials

- **Feature folders.** Domain code lives in `src/features/<domain>/` (mirrors
  `api/docs/domains/`); `src/components/` is for domain-agnostic **primitives** only
  (empty until repetition justifies extracting them); `src/pages/` are thin route
  compositions; `src/lib/` is cross-cutting (`api.js`, `i18n.js`).
- **Hooks fetch, components render.** All data access is in a feature's `api.js` hooks —
  never `fetch` inline in a component. Plain hooks for now; TanStack Query is the target.
- **Codes, not sentences.** `lib/api.js` throws `ApiError(code)`; components render
  `t(code)` via `lib/i18n.js`. i18n is deferred — `lib/i18n.js` is a placeholder map today.
- **Rule of three** before extracting a primitive. Don't abstract early.

## Runtime

- Dev server runs in Docker (`npm run dev`, Vite on 5173) behind nginx; HMR is configured
  for the `:8080` public port in `vite.config.js`.
