# Cross-language wire contracts

One fixture per JSON message that crosses a language boundary. Both sides test against
the **same file**, so renaming a field on one side turns that side's suite red — the
"change both sides in the same commit" rule, machine-enforced.

| Fixture | Producer (asserts exact bytes) | Consumer (asserts it parses) |
|---|---|---|
| `parse_job.json` | api — `App\Queue\RedisParseQueue` | worker — `cmd/worker` `Job` |
| `match_parsed.json` | worker — `internal/events.Event` | notifier — `internal/sub.Event` |

The producer test compares byte-for-byte (it defines the canonical serialization); the
consumer test decodes the fixture and checks every field survives. Containers see this
directory read-only at `../contracts` relative to each module root (compose mounts);
in CI the checkout provides the same relative path.
