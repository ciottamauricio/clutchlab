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
- The demo is **untrusted input**, so the parse runs under a **sandbox** (`parser.Limits`):
  a wall-clock timeout (`PARSE_TIMEOUT_SECONDS`) and heap ceiling (`PARSE_MEMORY_LIMIT_MB`),
  checked between frames; a breach → `parse_failed_timeout` / `parse_failed_memory`. Full
  isolation (separate process) is the next rung.
- Writes are **idempotent** (delete-then-insert stats), so re-delivered jobs don't double.
- Toolchain pinned to **Go 1.24**; `minio-go` and `air` pinned to match.
