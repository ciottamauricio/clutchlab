# Clutchlab worker (Go)

Background consumer — **no inbound HTTP, not behind nginx**. It BLPOPs the parse queue,
downloads the demo from MinIO, parses it with demoinfocs, and writes the scoreboard back
to the shared Postgres `matches` / `match_player_stats` tables.

- **How to build Go services here** (layout + conventions): [`docs/ENGINEERING.md`](docs/ENGINEERING.md)
- App-wide build & run: repo-root [`../docs/ENGINEERING.md`](../docs/ENGINEERING.md)
- The domain it writes to: [`../api/docs/domains/matches.md`](../api/docs/domains/matches.md)

## Essentials

- Layout is `cmd/worker/main.go` + `internal/{config,db,queue,storage,parser,matches}`.
- The queue is a **plain Redis list** (`demo_parse_jobs`) with JSON `{ match_id, demo_key }`,
  shared with Laravel — change both sides together.
- Parsing **recovers panics** into errors; a corrupt demo becomes `status=failed` /
  `error_code=parse_failed_corrupt`, never a crash.
- The demo is **untrusted input**, so the parse is sandboxed in layers: panic recovery,
  **resource limits** (`parser.Limits` — timeout + heap cap, `PARSE_TIMEOUT_SECONDS` /
  `PARSE_MEMORY_LIMIT_MB`, checked between frames → `parse_failed_timeout`/`_memory`), and
  **process isolation** (`PARSE_ISOLATION=true`) — the binary re-execs itself as
  `worker --parse-child` to parse one demo in a throwaway process (demo on stdin,
  ParseResult JSON on stdout, empty env). Off in dev (air has no binary to exec), on in
  prod. OS-level lockdown (no network, read-only FS) is the remaining rung.
- Writes are **idempotent** (delete-then-insert stats), so re-delivered jobs don't double.
- Toolchain pinned to **Go 1.24**; `minio-go` and `air` pinned to match.
