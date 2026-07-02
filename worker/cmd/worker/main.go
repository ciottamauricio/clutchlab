package main

import (
	"context"
	"encoding/json"
	"log"

	"clutchlab/worker/internal/config"
	"clutchlab/worker/internal/db"
	"clutchlab/worker/internal/matches"
	"clutchlab/worker/internal/parser"
	"clutchlab/worker/internal/queue"
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

	w := &worker{store: matches.NewStore(conn), storage: store}

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
		w.store.Fail(job.MatchID, "parse_failed_download")
		return
	}
	defer obj.Close()

	res, err := parser.ParseDemo(obj)
	if err != nil {
		log.Printf("match %d: parse failed: %v", job.MatchID, err)
		w.store.Fail(job.MatchID, "parse_failed_corrupt")
		return
	}

	if err := w.store.Save(job.MatchID, res); err != nil {
		log.Printf("match %d: save failed: %v", job.MatchID, err)
		w.store.Fail(job.MatchID, "parse_failed_internal")
		return
	}
	log.Printf("match %d: parsed %s %d-%d (%d players)", job.MatchID, res.MapName, res.ScoreCT, res.ScoreT, len(res.Players))
}
