package main

import (
	"context"
	"encoding/json"
	"errors"
	"io"
	"log"
	"os"
	"time"

	"clutchlab/worker/internal/config"
	"clutchlab/worker/internal/db"
	"clutchlab/worker/internal/events"
	"clutchlab/worker/internal/matches"
	"clutchlab/worker/internal/parser"
	"clutchlab/worker/internal/queue"
	"clutchlab/worker/internal/search"
	"clutchlab/worker/internal/storage"
	"clutchlab/worker/internal/telemetry"

	"go.opentelemetry.io/otel"
	"go.opentelemetry.io/otel/attribute"
	"go.opentelemetry.io/otel/codes"
	"go.opentelemetry.io/otel/trace"
)

// Job is the cross-language queue contract. It MUST stay byte-compatible with what
// the Laravel side rpushes (App\Queue\RedisParseQueue). See docs/ARCHITECTURE.md.
type Job struct {
	MatchID int64  `json:"match_id"`
	DemoKey string `json:"demo_key"`
	// Owner and filename now ride the job (the api knows both at enqueue) so the worker
	// needs zero access to the matches table — it owns only analytics.* after the DB split.
	OwnerID  int64  `json:"owner_id,omitempty"`
	Filename string `json:"filename,omitempty"`
	// W3C trace context from the api's enqueue span — additive, optional. When present,
	// parse_job joins the api's trace so Jaeger shows the whole upload as one waterfall.
	Traceparent string `json:"traceparent,omitempty"`
}

type worker struct {
	store     *matches.Store
	storage   *storage.Client
	indexer   search.Indexer
	events    events.Publisher
	limits    parser.Limits
	isolation bool
}

func main() {
	ctx := context.Background()
	cfg := config.Load()

	// Isolated-parse child mode: the parent re-execs the worker binary with this flag to
	// parse one demo in a throwaway process. It must run before any normal startup — no
	// DB, no Redis, no logging to stdout (stdout is reserved for the ParseResult JSON).
	if len(os.Args) > 1 && os.Args[1] == parseChildFlag {
		os.Exit(runParseChild(parser.Limits{
			Timeout:      time.Duration(cfg.ParseTimeoutSeconds) * time.Second,
			MaxHeapBytes: uint64(cfg.ParseMemoryLimitMB) * 1024 * 1024,
		}))
	}

	log.SetFlags(log.LstdFlags | log.Lmsgprefix)
	log.SetPrefix("[worker] ")

	conn, err := db.Connect(cfg.DSN())
	if err != nil {
		log.Fatal(err)
	}
	defer conn.Close()
	log.Print("connected to postgres")

	store, err := storage.New(cfg.MinioEndpoint, cfg.MinioKey, cfg.MinioSecret, cfg.Bucket)
	if err != nil {
		log.Fatal(err)
	}
	log.Print("minio client ready")

	consumer, err := queue.Connect(ctx, cfg.RedisAddr, cfg.Queue)
	if err != nil {
		log.Fatal(err)
	}
	defer consumer.Close()
	log.Print("connected to redis")

	publisher, err := events.NewRedis(ctx, cfg.RedisAddr, cfg.EventsChannel)
	if err != nil {
		log.Fatal(err)
	}
	defer publisher.Close()

	shutdown, err := telemetry.Init(ctx, "worker", cfg.OtelEndpoint)
	if err != nil {
		log.Printf("tracing disabled: %v", err)
	} else {
		defer shutdown(ctx)
	}

	w := &worker{
		store:   matches.NewStore(conn),
		storage: store,
		indexer: search.NewMeili(cfg.MeiliHost, cfg.MeiliKey),
		events:  publisher,
		limits: parser.Limits{
			Timeout:      time.Duration(cfg.ParseTimeoutSeconds) * time.Second,
			MaxHeapBytes: uint64(cfg.ParseMemoryLimitMB) * 1024 * 1024,
		},
		isolation: cfg.ParseIsolation,
	}
	log.Printf("parse sandbox: timeout=%ds memory=%dMB isolation=%v", cfg.ParseTimeoutSeconds, cfg.ParseMemoryLimitMB, cfg.ParseIsolation)

	log.Printf("ready, blocking on redis list %q", cfg.Queue)
	for {
		payload, err := consumer.Next(ctx)
		if err != nil {
			log.Printf("consume error: %v", err)
			continue
		}
		w.handle(ctx, payload)
	}
}

func (w *worker) handle(ctx context.Context, payload string) {
	var job Job
	if err := json.Unmarshal([]byte(payload), &job); err != nil {
		log.Printf("undecodable job dropped: %v (%s)", err, payload)
		return
	}
	log.Printf("match %d: parsing %s", job.MatchID, job.DemoKey)

	// Join the api's upload trace when the job carries one (a missing traceparent just
	// yields a local root trace). Each stage below is a child span, so Jaeger shows the
	// parse as a waterfall (download → parse → save → index) under the api's span.
	ctx = telemetry.Extract(ctx, job.Traceparent)
	ctx, span := otel.Tracer("worker").Start(ctx, "parse_job", trace.WithAttributes(
		attribute.Int64("match_id", job.MatchID),
		attribute.String("demo_key", job.DemoKey),
	))
	defer span.End()

	// No SetParsing here anymore: the worker no longer writes `matches` (it owns only
	// analytics.* since the DB split). The api sets status=parsing at enqueue; the worker
	// reports the terminal outcome as an event the api applies.

	obj, err := stageSpan(ctx, "download", func(c context.Context) (io.ReadCloser, error) {
		return w.storage.Download(c, job.DemoKey)
	})
	if err != nil {
		log.Printf("match %d: download failed: %v", job.MatchID, err)
		w.fail(ctx, span, job.MatchID, "parse_failed_download")
		return
	}
	defer obj.Close()

	res, err := stageSpan(ctx, "parse", func(c context.Context) (*parser.ParseResult, error) {
		// In prod the parse runs in a throwaway child process (crash/exploit stays there);
		// in dev it runs in-process under the same limits (air has no binary to exec).
		if w.isolation {
			return parseIsolated(c, obj, w.limits)
		}
		return parser.ParseDemoWithLimits(c, obj, w.limits)
	})
	if err != nil {
		log.Printf("match %d: parse failed: %v", job.MatchID, err)
		w.fail(ctx, span, job.MatchID, parseFailCode(err))
		return
	}

	// Write the scoreboard into analytics.* only. matches is the api's; its parsed summary
	// is applied by the api from the match.parsed event below.
	if _, err := stageSpan(ctx, "save", func(context.Context) (struct{}, error) {
		return struct{}{}, w.store.SaveStats(job.MatchID, res)
	}); err != nil {
		log.Printf("match %d: save failed: %v", job.MatchID, err)
		w.fail(ctx, span, job.MatchID, "parse_failed_internal")
		return
	}
	log.Printf("match %d: parsed %s %d-%d (%d players)", job.MatchID, res.MapName, res.ScoreCT, res.ScoreT, len(res.Players))

	// Project events into the search read model. Failure here is logged, not fatal —
	// the match is already parsed and the index can be rebuilt from Postgres.
	func() {
		ctx, s := otel.Tracer("worker").Start(ctx, "index")
		defer s.End()
		w.index(ctx, job.MatchID, res, job.OwnerID, job.Filename)
	}()

	// The api applies this to the matches row (the worker can't write it). Carries the
	// full summary the UPDATE used to set.
	w.publish(ctx, events.Event{
		Event: "match.parsed", V: 1, MatchID: job.MatchID,
		Demo: job.Filename, Map: res.MapName,
		ScoreCT: int(res.ScoreCT), ScoreT: int(res.ScoreT),
		CTName: res.CTName, TName: res.TName,
		TotalRounds: int(res.TotalRounds), TickRate: res.TickRate,
		DurationSeconds: res.DurationSeconds, KnifeRoundWinner: res.KnifeRoundWinner,
	})
}

// parseFailCode distinguishes a sandbox-limit breach from an ordinary corrupt demo, so
// the status the user sees says which. A timeout/memory kill is likely a hostile or
// pathological file, not just a broken one.
func parseFailCode(err error) string {
	switch {
	case errors.Is(err, parser.ErrParseTimeout), errors.Is(err, errChildTimeout):
		return "parse_failed_timeout"
	case errors.Is(err, parser.ErrParseMemory), errors.Is(err, errChildMemory):
		return "parse_failed_memory"
	default:
		// Includes errChildParse — a crashed/OOM-killed/corrupt child, all contained.
		return "parse_failed_corrupt"
	}
}

// stageSpan runs one step of the job inside its own child span, recording the error on it.
func stageSpan[T any](ctx context.Context, name string, fn func(context.Context) (T, error)) (T, error) {
	ctx, span := otel.Tracer("worker").Start(ctx, name)
	defer span.End()
	v, err := fn(ctx)
	if err != nil {
		span.RecordError(err)
		span.SetStatus(codes.Error, err.Error())
	}
	return v, err
}

// fail announces the failure as an event the api applies to the matches row — the worker
// can't write `matches` itself since the DB split. (At-most-once: a dropped match.failed
// leaves the row in 'parsing'; see the reconcile note in docs/plans/split-the-database.md.)
func (w *worker) fail(ctx context.Context, span trace.Span, matchID int64, code string) {
	span.SetStatus(codes.Error, code)
	w.publish(ctx, events.Event{Event: "match.failed", V: 1, MatchID: matchID, ErrorCode: code})
}

// publish is fire-and-forget by design: notifications must never hold a parse hostage,
// so a publish failure is logged and the loop moves on (same rule as search indexing).
// The traceparent rides the payload so the subscriber's span joins this trace.
func (w *worker) publish(ctx context.Context, e events.Event) {
	e.Traceparent = telemetry.Traceparent(ctx)
	if err := w.events.Publish(ctx, e); err != nil {
		log.Printf("match %d: event %s publish failed: %v", e.MatchID, e.Event, err)
	}
}

func (w *worker) index(ctx context.Context, matchID int64, res *parser.ParseResult, ownerID int64, filename string) {
	kills, rounds, err := w.store.SaveEvents(matchID, ownerID, filename, res)
	if err != nil {
		log.Printf("match %d: save events failed: %v", matchID, err)
		return
	}
	if err := w.indexer.DeleteMatch(ctx, matchID); err != nil {
		log.Printf("match %d: index delete failed: %v", matchID, err)
	}
	if err := w.indexer.IndexKills(ctx, kills); err != nil {
		log.Printf("match %d: index kills failed: %v", matchID, err)
	}
	if err := w.indexer.IndexRounds(ctx, rounds); err != nil {
		log.Printf("match %d: index rounds failed: %v", matchID, err)
	}
	log.Printf("match %d: indexed %d kills, %d rounds", matchID, len(kills), len(rounds))
}
