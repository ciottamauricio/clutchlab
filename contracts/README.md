# Cross-language wire contracts

One fixture per JSON message that crosses a language boundary. Both sides test against
the **same file**, so renaming a field on one side turns that side's suite red — the
"change both sides in the same commit" rule, machine-enforced.

| Fixture | Producer (asserts exact bytes) | Consumer (asserts it parses) |
|---|---|---|
| `parse_job.json` | api — `App\Queue\RedisParseQueue` | worker — `cmd/worker` `Job` |
| `match_parsed.json` | worker — `internal/events.Event` | notifier — `internal/sub.Event` |
| `training_scheduled.json` | api — `App\Queue\RedisEventBus` | notifier — `internal/sub.Event` |

The producer test compares byte-for-byte (it defines the canonical serialization); the
consumer test decodes the fixture and checks every field survives. Containers see this
directory read-only at `../contracts` relative to each module root (compose mounts);
in CI the checkout provides the same relative path.

## Optional trace context

Messages that cross a non-HTTP hop may carry an **optional** `traceparent` (W3C trace
context) so the consumer's span joins the producer's trace. It is additive and omitted
when no trace is active, so the canonical fixtures above don't include it — the byte
contract is the no-trace shape. `traceparent` is context only: it never carries data,
and both sides test that no payload field ever hides in it.
