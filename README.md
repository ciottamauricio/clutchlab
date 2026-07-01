# Clutchlab

A learning project for practicing **service-oriented / microservice architecture** with a
polyglot stack. Upload a Counter-Strike 2 demo (`.dem`), have it parsed asynchronously,
and view match stats, heatmaps, and (later) collaborative team tactics.

The point of this project is **the interaction between services**, not the features
themselves. Each service exists because its workload genuinely differs from the others —
see `docs/ARCHITECTURE.md` for the reasoning behind every boundary.

## Stack

| Service      | Tech                     | Why it exists                                            |
|--------------|--------------------------|----------------------------------------------------------|
| `nginx`      | nginx (reverse proxy)    | Single entry point; routes `/` → frontend, `/api` → api  |
| `frontend`   | React + Vite             | UI: upload, dashboards, charts                           |
| `api`        | Laravel (PHP 8.3)        | CRUD heart: auth, users, matches, teams — dev velocity   |
| `worker`     | Go 1.24                  | CPU-bound demo parsing — concurrency + the only mature Source 2 parser |
| `postgres`   | Postgres 16              | Primary datastore                                        |
| `redis`      | Redis 7                  | Cross-language job queue (plain list + JSON)             |
| `minio`      | MinIO (S3-compatible)    | Object storage for large `.dem` files                    |

Only `nginx` is exposed to the host. Everything else talks over a private Docker network
by **service name** (not `localhost`).

## Quick start

```bash
cp .env.example .env

# Scaffold the three projects into their folders (throwaway containers,
# so you don't need PHP/Node/Go installed on your machine):
docker run --rm -v "$PWD/api":/app -w /app composer:2 create-project laravel/laravel .
docker run --rm -v "$PWD/frontend":/app -w /app node:22-alpine npm create vite@latest . -- --template react
docker run --rm -v "$PWD/worker":/app -w /app golang:1.24-alpine go mod init clutchlab/worker

docker compose up --build

# Then, one-time Laravel setup:
docker compose exec api php artisan key:generate
docker compose exec api php artisan migrate
```

Create the `demos` bucket from the MinIO console at http://localhost:9001.

Open the app at http://localhost:8080.

## The two footguns (read these before debugging networking)

1. **nginx `proxy_pass` trailing slash.** `proxy_pass http://api:8000;` (no slash)
   preserves the `/api` prefix. Adding a slash rewrites it and Laravel 404s.
2. **Service names, not `localhost`.** Inside a container, `localhost` means *that
   container*. Use `postgres`, `redis`, `minio` as hostnames.

## Roadmap

See `docs/ROADMAP.md`. Short version, ordered so each step teaches exactly one new thing:

1. Core parser vertical slice (upload → queue → parse → dashboard)
2. Teams + auth/roles (domain modeling, authorization)
3. Real-time tactics board (websockets, a *second* Go service)
4. Search over parsed events (a synced read model / CQRS)
5. Notifications + Discord bot (event-driven pub-sub)
