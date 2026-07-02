# Go service engineering (Clutchlab)

How we build Go services in Clutchlab — the `worker` (batch demo parsing) and
`realtime` (websocket tactics), and any future one. App-wide principles and
infrastructure (Docker, nginx, config, cross-service contracts) live in the repo-root
[`../../docs/ENGINEERING.md`](../../docs/ENGINEERING.md); this is the Go-specific guide.

Each Go service is its **own module** (its own `go.mod`) and its **own top-level
directory** — services stay independent, per the monorepo principle in
`docs/ARCHITECTURE.md`. Small cross-service helpers (env config, DB/redis connect) are
**duplicated per service on purpose**: isolation over DRY. Do not create a shared Go
module to avoid the duplication — that couples the services.

## Layout (standard cmd/ + internal/)

```
<service>/
├── go.mod  go.sum
├── Dockerfile  .air.toml
├── cmd/<service>/main.go      # the only entrypoint: wire deps + run the loop/server
└── internal/
    ├── config/                # Config struct loaded from env (no config files)
    ├── db/                    # Postgres connect (with readiness retry)
    ├── <adapter>/             # one package per external system it talks to
    │                          #   worker: queue (redis), storage (minio)
    │                          #   realtime: auth (token check), ws (upgrade)
    └── <domain>/              # the actual logic
                               #   worker: parser (demoinfocs), matches (repo)
                               #   realtime: hub (rooms + broadcast)
```

`internal/` means the packages can't be imported by other modules — exactly right for a
service. `cmd/<service>` is the wiring layer and stays thin: build config, open
connections, hand them to the domain packages, run.

## Conventions

- **Config from the environment only** (`config.Load()`), injected by Compose `env_file`.
  No config files, no flags for normal operation. Provide sane localhost defaults.
- **Constructor-style dependencies.** Packages expose `New(...)`/`Connect(...)` returning
  a struct; `main` wires them together. No global singletons.
- **Wait for dependencies.** `depends_on` doesn't wait for readiness, so `db.Connect` /
  `queue.Connect` poll with a short backoff before giving up.
- **Recover around untrusted work.** Anything that can panic on bad input (e.g. demo
  parsing) recovers the panic into an `error` so one bad job can't kill the process.
  See `internal/parser`.
- **Codes, not sentences.** A service writes status/error *codes* (e.g. `parse_failed_corrupt`)
  — the frontend localizes them. Same rule as the other services.
- **Structured-ish logging.** `log.SetPrefix("[worker] ")`; log one line per job/connection
  with the id, so the flow is greppable.
- **Hot reload in dev via `air`** (`.air.toml` builds `./cmd/<service>`), pinned to a
  Go 1.24-compatible version. Source is bind-mounted; edits rebuild automatically.

## Cross-service contracts

- The queue payload and the shared DB schema are contracts with the other services.
  Change both sides in the same commit (`docs/ARCHITECTURE.md`).
- Services that need to authenticate a user validate the **Sanctum token against the
  shared `personal_access_tokens` table** (sha256 of the token's plaintext part) — the
  same tokens Laravel issues. See `realtime/internal/auth`.

## Testing

- Table-driven tests; keep pure logic (e.g. parsing tallies) testable without I/O.
- Mock an external system behind a small interface; never make real network calls in tests.
- No test demos in the repo — parsing is exercised against fixtures locally.

## Recipe: adding a Go service

1. New top-level dir with its own `go.mod` (`go mod init clutchlab/<service>`).
2. Copy the `Dockerfile` + `.air.toml` (point `air` at `./cmd/<service>`).
3. `cmd/<service>/main.go` — wire config + connections, run.
4. `internal/config` for env; one `internal/<adapter>` per external system; `internal/<domain>`
   for logic.
5. Add the service to `docker-compose.yml` (`env_file: .env`, `networks: [clutchnet]`,
   `depends_on` its backends). Expose it to the host **only** through nginx if it serves HTTP/ws.
6. Recover panics around untrusted input; return codes; log per unit of work.
