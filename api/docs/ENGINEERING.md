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

**Two exceptions, on purpose:** `app/PlatformSync/` (FACEIT sync, spec 002) and `app/PlayerStats/`
(stats and awards, spec 004) are built in ports and adapters, with their layers enforced by a
check, to compare with this style on the same codebase. See
[the modules' section](#a-ports-and-adapters-module-appplatformsync) and
[the second module](#the-second-module-appplayerstats) below.

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
(the gate and CI) runs PHPat's rules, written once in
[`tests/Architecture/ModuleLayers.php`](../tests/Architecture/ModuleLayers.php) and registered per
module (`PlatformSyncLayers`, `PlayerStatsLayers`, each naming its namespace and provider):
1. the domain depends only on itself;
2. the ports only on the domain;
3. the use cases only on the domain and the ports;
4. the adapters never on the use cases;
5. outside the adapters, only `PlatformSyncServiceProvider` names one.

The first three rules are allow-lists, so a facade in any form (imported, a root alias, a
real-time `Facades\…`) fails them like any other outside class. Laravel's helpers (`config()`,
`now()`, `collect()`…) are functions, so no class rule sees them. [`phpstan.neon`](../phpstan.neon)
also refuses every function defined under `vendor/laravel/framework` in each module's domain,
ports and use cases. One module's core can't use the other's either: the allow-lists name only
the module's own layers. What a use case needs from config arrives through its constructor (`SyncSettings`,
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

## The second module: `app/PlayerStats/`

Stats and awards (spec 004): the team board, the profile, the awards and their drill-down, the
players lists, a player's clutches, and the match page's comparison and clutches. The same layers
and rules as `PlatformSync`, wired by `PlayerStatsServiceProvider`. What's different is that its
main port is a query over the worker's read model, and its counting stays SQL.

| Layer | Holds |
|---|---|
| `Domain/` | every counting and derived rule as plain PHP: `Weapons::ECO`, `HitGroups::legsDominant`, `Clutches::fromKills`, `StatLine` (K/D, HS%), `AwardRules` (floors, top 5), `Ranking` and `TopWeapon` (the tie-breaks), `Outcome`; and the values (`MatchInfo`, `Kill`, `PlayerCounts`…) |
| `Ports/` | `Matches` and `Rosters` (the api's data, `public`), `MatchEvents` (the worker's, `analytics`) |
| `Application/` | one use case per answer: `TeamBoard`, `PlayerProfile`, `Awards`, `AwardKills`, `PlayerList`, `PlayerClutches`, `MatchSides`, `MatchClutches`, and `PlayerTotals` (per-player totals over any set of matches, which the board uses and the analyst can) |
| `Adapters/` | `Eloquent\EloquentMatches`, `Eloquent\EloquentRosters`, `Postgres\PostgresMatchEvents` |

The HTTP side stays as it was: the routes, the controllers' authorization and lenient parameter
parsing (no Form Requests: a rejecting one would change the answers), and Resources in
`app/Http/Resources/PlayerStats/` that write each answer's keys and types as before, including the
profile's raw `tick_rate` and jsonb-text `hitgroups` (`JsonbText`).

**One definition, two implementations, one contract.** A rule is defined once, in the domain.
`InMemoryMatchEvents` (`tests/Fakes/PlayerStats/`) counts with those PHP rules, and the use-case
tests trust it. `PostgresMatchEvents` counts the same things in SQL, each fragment written once.
[`MatchEventsContractTest`](../tests/Postgres/PlayerStats/MatchEventsContractTest.php) asks both
every question over the same scenario and requires the same answers. That test needs Postgres,
which is why the Postgres suite exists (see Testing below).

**The ownership line, kept inside the api.** `MatchEvents` is given match ids, plus their upload
order for the latest name, and joins no `public` table. The match facts the answers print (map,
dates, demo, tick rate) come through `Matches`, and the use case puts them together. The contract
test runs the Postgres adapter with only `analytics` on the `search_path`, so a join to `matches`
fails there. When the database split's phase 3 moves `analytics` to its own server, only the
adapter's connection changes.

Two things that bite:
- **Numeric Steam IDs turn into ints as array keys.** Values carry their `steamId`, and maps keyed
  by Steam ID use `'#'.$steamId`.
- **Names are ordered by the database.** There's no `intl` in the image, and bytewise order
  differs from `en_US.utf8` for most names. So `MatchEvents::players()` returns them already in
  the database's order, and the Postgres suite pins it (`NameOrderTest`).

Testing it:
- **Domain and use cases:** `tests/Unit/PlayerStats/`, on PHPUnit's own `TestCase`, over
  [`tests/Fixtures/PlayerStats/Scenario.php`](../tests/Fixtures/PlayerStats/Scenario.php) loaded
  into memory. The scenario holds the data's corner cases (self-kills, bomb kills, team kills,
  tied openings, ties at every cut); it can also write itself to a database.
- **Adapters:** `tests/Feature/PlayerStats/` (the `Matches` and `Rosters` contracts, on sqlite)
  and `tests/Postgres/PlayerStats/` (`MatchEvents`, on Postgres).
- **The HTTP contract:** `tests/Postgres/Characterization/`, one class per endpoint. These were
  written against the old code before it moved, and kept their requests and assertions through the
  switch.

## Testing (api)

> Step 1 shipped without tests; this is the standard for tests written from here.

- Mock **interfaces**, not concrete classes.
- Unit-test an Action/service by mocking only its injected dependency; never make real
  external calls.
- Use an in-memory SQLite DB for feature tests. `tests/TestCase` refuses anything else before
  `RefreshDatabase` migrates (`setUpTraits()`), and again after (the tripwire in `setUp()`).
- **SQL that needs Postgres** is tested in its own suite, [`phpunit.pgsql.xml`](../phpunit.pgsql.xml)
  (`tests/Postgres/`), against a throwaway server, never the dev one:
  ```bash
  docker compose --profile test up -d postgres-test        # once; tmpfs, gone on stop
  docker compose exec -T api ./vendor/bin/phpunit -c phpunit.pgsql.xml
  ```
  Its tests extend [`Tests\Postgres\PostgresTestCase`](../tests/Postgres/PostgresTestCase.php),
  not `Tests\TestCase`. Before anything migrates, it asks the server it reached for two things:
  the database must be `clutchlab_test`, and there must be no `clutchlab` database on that
  server. If the server isn't up, the suite fails on the connection. CI runs it against a
  service container with the same image.
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
