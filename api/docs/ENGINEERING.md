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

**One exception, on purpose:** `app/PlatformSync/` (FACEIT sync) is built in ports and adapters,
with its layers enforced by a check, as a pilot to compare with this style on the same codebase.
See [the module's section](#a-ports-and-adapters-module-appplatformsync) below.

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

## A ports-and-adapters module: `app/PlatformSync/`

FACEIT sync (the matches page's FACEIT panel, and FACEIT's time in the upload form) is the one
module built in **ports and adapters** instead of the Action style above (spec 002). The
rest of the api stays as it is. The point is to compare the two shapes on the same codebase, not
to convert everything.

```
HTTP ─▶ Route ─▶ Form Request ─▶ FaceitController ─▶ Application (use case) ─▶ Ports ◀── Adapters
                                 (FACEIT's codes)     FindAccount              (interfaces)   FaceitDataApi (HTTP)
                                                      ListMatches                             Eloquent… (Postgres)
                                                      GetMatch      ─▶ Domain                 LaravelCache
                                                                       (values, row rules)
                        PlatformSyncServiceProvider binds every port to its adapter
```

| Layer | Holds | May depend on |
|---|---|---|
| `Domain/` | values (`SteamId`, `PlatformMatchId`, `PlayedMatch`, `MatchFacts`…) and the panel row's rules (`MatchRow`) | itself, PHP's own classes, Carbon |
| `Ports/` | interfaces named for what the use cases need (`PlatformPlayers`, `PlatformMatches`, `PlatformAccounts`, `ClutchlabMatches`, `Cache`) and their failure, `PlatformUnavailable` | the domain |
| `Application/` | the use cases (`FindAccount`, `ListMatches`, `GetMatch`) and their inputs and results (`Viewer`, `SyncSettings`, `MatchPanel`…) | the domain and the ports |
| `Adapters/` | `Faceit\FaceitDataApi`, `Eloquent\EloquentPlatformAccounts`, `Eloquent\EloquentClutchlabMatches`, `Laravel\LaravelCache` | anything: Laravel, Eloquent, HTTP. Never the use cases |

The driving side stays Laravel's, and stays FACEIT's: the routes, `ListFaceitMatchesRequest`,
`FaceitController` (it builds the `Viewer` and turns the module's answers into `faceit.*` codes)
and `FaceitMatchResource`. The ports and values are platform-neutral, with FACEIT as the only
adapter.

**The dependency rule is checked, not trusted.** `./vendor/bin/phpstan analyse --memory-limit=1G`
(the gate and CI) runs PHPat's rules in
[`tests/Architecture/PlatformSyncLayers.php`](../tests/Architecture/PlatformSyncLayers.php):
1. the domain depends only on itself;
2. the ports only on the domain;
3. the use cases only on the domain and the ports;
4. the adapters never on the use cases;
5. outside the adapters, only `PlatformSyncServiceProvider` names one.

The first three rules are allow-lists, so a facade in any form (imported, a root alias, a
real-time `Facades\…`) fails them like any other outside class. Laravel's helpers (`config()`,
`now()`, `collect()`…) are functions, so no class rule sees them. [`phpstan.neon`](../phpstan.neon)
also refuses every function defined under `vendor/laravel/framework` in the domain, ports and
use cases. What a use case needs from config arrives through its constructor (`SyncSettings`,
built by the provider).

How it differs from the Action style:

| | The Action style (the rest of the api) | `app/PlatformSync/` |
|---|---|---|
| A use case is | an Action in `app/Actions/` | a class in `Application/` |
| External systems | an interface in `app/Contracts/`, often shaped like the vendor | a port in `Ports/`, named and typed for the use case |
| Persistence and cache | Eloquent and Laravel's cache, called from the Action | ports (`PlatformAccounts`, `ClutchlabMatches`, `Cache`) with Eloquent and Laravel adapters |
| Wired in | `AppServiceProvider` | `PlatformSyncServiceProvider` |
| Rules tested | through the database (feature tests on sqlite) | in memory, with no app booted (`tests/Unit/PlatformSync/`) |
| Layering | a convention | a failing check |
| Size for FACEIT | 9 files, ~510 lines | 30 files, ~1,000 lines, plus contract tests and an in-memory adapter per port |

Testing it:
- **Domain and use cases:** `tests/Unit/PlatformSync/` on PHPUnit's own `TestCase`, with the
  in-memory adapters in [`tests/Fakes/PlatformSync/`](../tests/Fakes/PlatformSync/). There's no
  app, container or database there to reach.
- **Adapters:** `tests/Feature/PlatformSync/` runs one contract test per stored port (accounts,
  Clutchlab matches, cache) on both the in-memory adapter and the real one, so a fake can't drift
  from what production does. `FaceitDataApi` is tested against `Http::fake`.
- **The HTTP contract:** `tests/Feature/FaceitMatchesTest.php`. The base `TestCase` puts
  `FakePlatform` in front of the platform ports and gives the instance a placeholder FACEIT key.

Two things that bite:
- **The cache keeps plain arrays and booleans only.** `config/cache.php` sets
  `serializable_classes => false`, so the database and Redis stores unserialize no objects. The
  tests' array store doesn't serialize at all, so an object would pass the suite and break dev.
  Cached values go through `toArray()` / `fromArray()`, and `InMemoryCache` refuses an object the
  way dev would.
- **A fake that drifts from its port kills the whole suite** with "Premature end of PHP process".
  The contract tests exist to catch that first.

Adding a platform (Valve matchmaking, say):
1. add a `Platform` case;
2. write adapters for `PlatformPlayers` and `PlatformMatches`;
3. give `EloquentPlatformAccounts` its table: the `match` there won't compile until it has one;
4. bind them in `PlatformSyncServiceProvider`. Choosing an adapter per platform comes with the
   second platform, not before.

Its routes, controller and codes are its own driving side.

## Testing (api)

> Step 1 shipped without tests; this is the standard for tests written from here.

- Mock **interfaces**, not concrete classes.
- Unit-test an Action/service by mocking only its injected dependency; never make real
  external calls.
- Use an in-memory SQLite DB for feature tests.
- The architecture check is its own command, not part of PHPUnit:
  `./vendor/bin/phpstan analyse --memory-limit=1G`. It runs PHPStan at level 0 with the layer rules
  in [`tests/Architecture/`](../tests/Architecture/) (PHPat) and a ban on Laravel's functions
  (`config()`, `now()`, …) in `app/PlatformSync`'s core, configured in
  [`phpstan.neon`](../phpstan.neon). The gate and CI run it next to Pint and PHPUnit. Level 0 checks
  dependencies, not types, but over all of `app/`, so an unknown class or function anywhere fails
  it too.

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
