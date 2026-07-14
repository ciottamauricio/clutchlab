# API engineering (Laravel)

How to build inside the Clutchlab api. This is the Laravel-specific layering and
conventions.

- **App-wide** principles, infrastructure (Docker, nginx, config, networking) and
  cross-service contracts: repo-root [`../../docs/ENGINEERING.md`](../../docs/ENGINEERING.md).
- **Domain business rules:** [`domains/`](domains/) — read the relevant domain doc before
  changing that domain.

## Request lifecycle & layers

```
HTTP ─▶ Route ─▶ Form Request ─▶ Controller ─▶ Action ─▶ ┌ Contracts (interfaces) ┐ ─▶ Resource ─▶ JSON
                 (validate)      (thin)        (1 op)     └ Eloquent Models        ┘
```

| Layer | Responsibility | Never |
|---|---|---|
| Route (`routes/api.php`) | Map URL → controller; attach throttle/auth middleware | Contain logic |
| Form Request | Validate + authorize input; emit **codes** | Touch the DB / do work |
| Controller | Wire request → action → resource | Hold business logic |
| Action (`app/Actions/`) | Exactly one operation; orchestrate models + contracts | Know about HTTP |
| Contract (`app/Contracts/`) + impl | Abstract an external system (storage, queue, API) | Leak the vendor SDK upward |
| Model | Persistence + relations | Make external calls |
| Resource | Shape the JSON response | Contain logic |

**Domain subfolders** (adopted with the trainings domain): new or next-touched domains
group their Actions and Requests per domain — `app/Actions/Trainings/`,
`app/Http/Requests/Trainings/` — so the tree drifts toward the domain docs without a
big-bang restructure. Layers stay the top level; the domain is the second level. Folders
don't enforce boundaries — the domain docs' invariants do; this is navigation, not DDD.

## Worked example — the demo upload (build this shape every time)

- Route: [`routes/api.php`](../routes/api.php) — `POST /matches` (throttled)
- Validate: [`UploadDemoRequest`](../app/Http/Requests/UploadDemoRequest.php) — returns `demo.*` codes
- Controller: [`MatchController@store`](../app/Http/Controllers/MatchController.php) — 3 lines
- Action: [`UploadDemoAction`](../app/Actions/UploadDemoAction.php) — store → persist → enqueue
- Contracts: [`DemoStorage`](../app/Contracts/DemoStorage.php)/[`S3DemoStorage`](../app/Storage/S3DemoStorage.php)
  and [`ParseQueue`](../app/Contracts/ParseQueue.php)/[`RedisParseQueue`](../app/Queue/RedisParseQueue.php),
  bound in [`AppServiceProvider`](../app/Providers/AppServiceProvider.php)
- Model: [`GameMatch`](../app/Models/GameMatch.php) · Response: [`MatchResource`](../app/Http/Resources/MatchResource.php)

## Backend rules

- Validation → **Form Request classes**, never inline `$request->validate()`.
- Business logic → **Action classes**, one class per operation.
- External integrations → **interface + concrete**, bound in `AppServiceProvider`; type-hint
  the interface, never the concrete class. Inject via the constructor.
- The api returns **codes, not sentences** (`demo.file_too_large`, status `parse_failed_corrupt`);
  the frontend localizes them.
- Ownership violations return **403**, not 404. Rate-limit auth endpoints. Keep
  `APP_DEBUG=false` in production.

## Testing (api)

> Step 1 shipped without tests; this is the standard for tests written from here.

- Mock **interfaces**, not concrete classes.
- Unit-test an Action/service by mocking only its injected dependency; never make real
  external calls.
- Use an in-memory SQLite DB for feature tests.

## Recipe: adding an api feature

1. Read — and if rules change, **update** — the domain doc in [`domains/`](domains/) first.
2. Add the Model + migration if there's new data.
3. Create a **Form Request** for the input (emit codes).
4. Create an **Action class** for the operation; constructor-inject its dependencies.
5. New external system? Add an **interface** in `app/Contracts/` + a concrete impl, and
   **bind** it in `AppServiceProvider`.
6. Add a **thin** controller method, a **Resource** for output, and the route (+throttle/auth).
7. Enforce authorization (403 for ownership).
8. Tests: mock interfaces; SQLite feature test.

For the frontend half of a feature see [`../../frontend/ENGINEERING.md`](../../frontend/ENGINEERING.md);
for changes that cross a service boundary (e.g. the parse-queue payload) follow the
cross-cutting flow in [`../../docs/ENGINEERING.md`](../../docs/ENGINEERING.md).
