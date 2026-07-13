package main

import (
	"context"
	"encoding/json"
	"log"

	"clutchlab/worker/internal/config"
	"clutchlab/worker/internal/db"
	"clutchlab/worker/internal/events"
	"clutchlab/worker/internal/matches"
	"clutchlab/worker/internal/parser"
	"clutchlab/worker/internal/queue"
	"clutchlab/worker/internal/search"
	"clutchlab/worker/internal/storage"
)

// Job is the cross-language queue contract. It MUST stay byte-compatible with what
// the Laravel side rpushes (App\Queue\RedisParseQueue). See docs/ARCHITECTURE.md.
type Job struct {
	MatchID int64  `json:"match_id"`
	DemoKey string `json:"demo_key"`
}

type worker struct {
	store   *matches.Store
	storage *storage.Client
	indexer search.Indexer
	events  events.Publisher
}

func main() {
	log.SetFlags(log.LstdFlags | log.Lmsgprefix)
	log.SetPrefix("[worker] ")

	ctx := context.Background()
	cfg := config.Load()

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

	w := &worker{
		store:   matches.NewStore(conn),
		storage: store,
		indexer: search.NewMeili(cfg.MeiliHost, cfg.MeiliKey),
		events:  publisher,
	}

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

	if err := w.store.SetParsing(job.MatchID); err != nil {
		log.Printf("match %d: set parsing failed: %v", job.MatchID, err)
	}

	obj, err := w.storage.Download(ctx, job.DemoKey)
	if err != nil {
		log.Printf("match %d: download failed: %v", job.MatchID, err)
		w.fail(ctx, job.MatchID, "parse_failed_download")
		return
	}
	defer obj.Close()

	res, err := parser.ParseDemo(obj)
	if err != nil {
		log.Printf("match %d: parse failed: %v", job.MatchID, err)
		w.fail(ctx, job.MatchID, "parse_failed_corrupt")
		return
	}

	if err := w.store.Save(job.MatchID, res); err != nil {
		log.Printf("match %d: save failed: %v", job.MatchID, err)
		w.fail(ctx, job.MatchID, "parse_failed_internal")
		return
	}
	log.Printf("match %d: parsed %s %d-%d (%d players)", job.MatchID, res.MapName, res.ScoreCT, res.ScoreT, len(res.Players))

	// Project events into the search read model. Failure here is logged, not fatal —
	// the match is already parsed and the index can be rebuilt from Postgres.
	w.index(ctx, job.MatchID, res)

	w.publish(ctx, events.Event{
		Event: "match.parsed", V: 1, MatchID: job.MatchID,
		Demo: w.store.Filename(job.MatchID), Map: res.MapName,
		ScoreCT: int(res.ScoreCT), ScoreT: int(res.ScoreT),
	})
}

// fail marks the match failed and announces the fact. The status write is the source
// of truth; the event is best-effort on top of it.
func (w *worker) fail(ctx context.Context, matchID int64, code string) {
	w.store.Fail(matchID, code)
	w.publish(ctx, events.Event{Event: "match.failed", V: 1, MatchID: matchID, ErrorCode: code})
}

// publish is fire-and-forget by design: notifications must never hold a parse hostage,
// so a publish failure is logged and the loop moves on (same rule as search indexing).
func (w *worker) publish(ctx context.Context, e events.Event) {
	if err := w.events.Publish(ctx, e); err != nil {
		log.Printf("match %d: event %s publish failed: %v", e.MatchID, e.Event, err)
	}
}

func (w *worker) index(ctx context.Context, matchID int64, res *parser.ParseResult) {
	ownerID := w.store.OwnerID(matchID)
	kills, rounds, err := w.store.SaveEvents(matchID, ownerID, res)
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
