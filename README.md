# Clutchlab

Upload a Counter-Strike 2 demo (`.dem`), have it parsed asynchronously, and explore the
match: scoreboards, kill heatmaps on the real radar, clutches, cross-match awards, a
collaborative tactics board, team practice scheduling with nade homework — and a Discord
ping (or roster email) when things happen.

The features are the excuse. This is a **learning project about the boundaries between
services**: a polyglot, service-oriented app where every seam — the cross-language queue,
the pub/sub event channel, the search read model, the wire contracts — exists to be
studied. The app documents itself: the **Study page** (in the app's nav) walks through
all sixteen architecture tradeoffs with what each decision gained and what it cost, and
[`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) is the written version.

## Services

| Service           | Tech                  | Why it exists                                                        |
|-------------------|-----------------------|----------------------------------------------------------------------|
| `nginx`           | nginx                 | The only exposed container; routes `/` → frontend, `/api` → api, `/realtime` → realtime |
| `frontend`        | React + Vite          | UI; the only place backend codes become human sentences               |
| `api`             | Laravel (PHP 8.3)     | CRUD heart: auth, teams, matches, trainings; owns Postgres            |
| `worker`          | Go 1.24               | CPU-bound demo parsing (demoinfocs), consumes the queue               |
| `realtime`        | Go                    | Tactics-board websockets                                              |
| `notifier`        | Go                    | Subscribes to events → Discord webhook (log-only without a URL)       |
| `events-listener` | Laravel (api image)   | Subscribes to the same events → email (log mailer by default)         |
| `postgres`        | Postgres 16           | Primary datastore                                                     |
| `redis`           | Redis 7               | Two jobs, two primitives: parse queue (list) + event channel (pub/sub)|
| `minio`           | MinIO (S3 API)        | Object storage for the `.dem` files (bucket auto-created on boot)     |
| `meilisearch`     | Meilisearch           | Search read model, synced by the worker                               |
| `loki` `alloy` `grafana` `jaeger` | —     | Observability: logs + traces across the service boundary              |

Services talk over a private Docker network **by service name, never `localhost`**.

## Install & run

Everything runs in containers — you only need **Docker with Compose** (and `git`).

```bash
git clone https://github.com/ciottamauricio/clutchlab.git
cd clutchlab

# 1. Config — one env file at the repo root feeds every service
cp .env.example .env

# 2. PHP dependencies (api code is bind-mounted, so vendor/ lives on your checkout)
docker compose run --rm api composer install

# 3. Generate the Laravel APP_KEY and paste it into the root .env
docker compose run --rm api php artisan key:generate --show
#    → copy the output into .env:  APP_KEY=base64:...

# 4. Bring everything up
docker compose up --build -d

# 5. One-time database + search setup
docker compose exec api php artisan migrate --seed
docker compose exec api php artisan search:setup
```

Open **http://localhost:8080**, register an account, and upload a `.dem`.

To use the admin panel (roles, permissions), promote your account once:

```bash
docker compose exec api php artisan tinker --execute='
  $u = App\Models\User::where("email", "you@example.com")->first();
  $u->role = "admin"; $u->save(); echo "admin";'
```

### Handy URLs (dev conveniences)

| URL                      | What                                          |
|--------------------------|-----------------------------------------------|
| http://localhost:8080    | the app                                       |
| http://localhost:9001    | MinIO console (`minioadmin` / `minioadmin`)   |
| http://localhost:3001    | Grafana — logs from every service (Loki)      |
| http://localhost:16686   | Jaeger — traces across the parse pipeline     |
| http://localhost:7700    | Meilisearch                                   |

### Optional integrations (env only — never commit secrets)

- `DISCORD_WEBHOOK_URL` — set it and the notifier posts parse results and scheduled
  trainings to your Discord channel; empty means it logs instead.
- `MAIL_MAILER` — `log` by default (roster emails land in `api/storage/logs`, visible in
  Grafana). Point it at real SMTP to actually send.

The credentials in `.env.example` (Postgres, MinIO, Meilisearch) are local-dev values for
containers that are never exposed beyond `nginx` — fine for a laptop, replace them for
anything else.

## The three footguns (read before debugging)

1. **Never create `api/.env`.** Config comes from the repo-root `.env` via Compose
   `env_file`; a stray `api/.env` silently overrides it for `artisan` only, and the two
   worlds disagree. (This is why step 3 uses `--show` + paste.)
2. **nginx `proxy_pass` trailing slash.** `proxy_pass http://api:8000;` (no slash)
   preserves the `/api` prefix. Adding a slash rewrites it and Laravel 404s.
3. **Service names, not `localhost`.** Inside a container, `localhost` means *that
   container*. It's `postgres`, `redis`, `minio` — always.

## Tests

```bash
docker compose exec api php artisan test        # feature + contract tests (in-memory sqlite)
docker compose exec frontend npm run lint
docker compose run --rm worker go test ./...
docker compose run --rm notifier go test ./...
```

The highest-leverage tests are the **wire-contract fixtures** in [`contracts/`](contracts/):
the producer asserts it emits exactly those bytes, each consumer asserts it decodes them —
in whatever language it speaks. CI runs per service with path filters; touching
`contracts/**` re-runs every suite that speaks the channel.

## `infra/` — the same architecture, written for the cloud

`docker-compose.yml` is the **local truth**; each directory under [`infra/`](infra/) is
the identical service graph rewritten as a **cloud truth** in Terraform — the diff
between the two files is the whole "compose vs. cloud" lesson (study topic 14):

```
infra/
├── azure/            # Azure Container Apps translation
│   ├── main.tf       #   the service graph: one resource per compose service —
│   │                 #   registry, network, Postgres, Redis, storage, and a
│   │                 #   container app per service (nginx stays the sole ingress)
│   ├── variables.tf  #   the knobs: region, SKUs, image tag — and the secrets
│   │                 #   (db password, APP_KEY, Meili key), injected as TF_VAR_*
│   │                 #   at plan time, never written into the files
│   └── outputs.tf    #   what you get back: the public URL, registry address,
│                     #   connection endpoints
└── aws/              # ECS Fargate translation — same three files, same roles;
                      # plus what Azure hides: explicit VPC, subnets, NAT,
                      # security groups, IAM task roles, Cloud Map DNS
```

Both are **validated skeletons** (`terraform validate` passes), not battle-tested
deployments — study material first. The interesting differences (where Redis forces TLS,
where MinIO→S3 is config-only vs. needs code, what `depends_on` not existing costs you)
are tabled in [`infra/README.md`](infra/README.md), along with how to `terraform plan`
one of them.

## Where to read more

- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — why every boundary is where it is
- [`docs/ENGINEERING.md`](docs/ENGINEERING.md) — build/run detail, conventions, config flow
- [`docs/ROADMAP.md`](docs/ROADMAP.md) — the step-by-step path (each step teaches one concept)
- [`api/docs/domains/`](api/docs/domains/) — business rules per domain; the code follows the doc
- **The Study page in the app** — the tradeoffs, as a feature
