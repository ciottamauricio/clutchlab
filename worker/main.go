package main

import (
	"context"
	"database/sql"
	"encoding/json"
	"fmt"
	"log"
	"net/url"
	"os"
	"strconv"
	"time"

	"github.com/minio/minio-go/v7"
	"github.com/minio/minio-go/v7/pkg/credentials"
	"github.com/redis/go-redis/v9"

	_ "github.com/lib/pq"
)

// Job is the cross-language queue contract. It MUST stay byte-compatible with
// what the Laravel side rpushes (App\Queue\RedisParseQueue). See docs/ARCHITECTURE.md.
type Job struct {
	MatchID int64  `json:"match_id"`
	DemoKey string `json:"demo_key"`
}

type deps struct {
	db     *sql.DB
	minio  *minio.Client
	bucket string
}

func main() {
	log.SetFlags(log.LstdFlags | log.Lmsgprefix)
	log.SetPrefix("[worker] ")

	ctx := context.Background()

	db := mustConnectPostgres()
	defer db.Close()

	mc := mustConnectMinio()
	rdb := mustConnectRedis(ctx)
	defer rdb.Close()

	d := &deps{db: db, minio: mc, bucket: env("AWS_BUCKET", "demos")}
	queue := env("PARSE_QUEUE", "demo_parse_jobs")

	log.Printf("ready, blocking on redis list %q", queue)
	for {
		// 0 = block forever until a job arrives.
		vals, err := rdb.BLPop(ctx, 0, queue).Result()
		if err != nil {
			log.Printf("BLPOP error: %v (retrying in 1s)", err)
			time.Sleep(time.Second)
			continue
		}
		d.handle(ctx, vals[1])
	}
}

func (d *deps) handle(ctx context.Context, payload string) {
	var job Job
	if err := json.Unmarshal([]byte(payload), &job); err != nil {
		log.Printf("undecodable job dropped: %v (%s)", err, payload)
		return
	}
	log.Printf("match %d: parsing %s", job.MatchID, job.DemoKey)

	d.mustExec("UPDATE matches SET status='parsing', updated_at=now() WHERE id=$1", job.MatchID)

	obj, err := d.minio.GetObject(ctx, d.bucket, job.DemoKey, minio.GetObjectOptions{})
	if err != nil {
		log.Printf("match %d: get object failed: %v", job.MatchID, err)
		d.fail(job.MatchID, "parse_failed_download")
		return
	}
	defer obj.Close()

	res, err := ParseDemo(obj)
	if err != nil {
		log.Printf("match %d: parse failed: %v", job.MatchID, err)
		d.fail(job.MatchID, "parse_failed_corrupt")
		return
	}

	if err := d.save(job.MatchID, res); err != nil {
		log.Printf("match %d: save failed: %v", job.MatchID, err)
		d.fail(job.MatchID, "parse_failed_internal")
		return
	}
	log.Printf("match %d: parsed %s %d-%d (%d players)", job.MatchID, res.MapName, res.ScoreCT, res.ScoreT, len(res.Players))
}

// save writes the whole result atomically. Delete-then-insert makes redelivery
// of the same job idempotent instead of doubling a player's stats.
func (d *deps) save(matchID int64, res *ParseResult) error {
	tx, err := d.db.Begin()
	if err != nil {
		return err
	}
	defer tx.Rollback()

	if _, err := tx.Exec("DELETE FROM match_player_stats WHERE match_id=$1", matchID); err != nil {
		return err
	}

	stmt, err := tx.Prepare(`INSERT INTO match_player_stats
		(match_id, steam_id, name, team_side, kills, deaths, assists, headshots)
		VALUES ($1,$2,$3,$4,$5,$6,$7,$8)`)
	if err != nil {
		return err
	}
	defer stmt.Close()

	for _, p := range res.Players {
		if _, err := stmt.Exec(matchID, strconv.FormatUint(p.SteamID, 10), p.Name, p.TeamSide,
			p.Kills, p.Deaths, p.Assists, p.Headshots); err != nil {
			return err
		}
	}

	if _, err := tx.Exec(`UPDATE matches SET
		status='parsed', error_code=NULL,
		map_name=$2, score_ct=$3, score_t=$4, ct_name=$5, t_name=$6, total_rounds=$7,
		parsed_at=now(), updated_at=now()
		WHERE id=$1`,
		matchID, res.MapName, res.ScoreCT, res.ScoreT, res.CTName, res.TName, res.TotalRounds); err != nil {
		return err
	}

	return tx.Commit()
}

func (d *deps) fail(matchID int64, code string) {
	d.mustExec("UPDATE matches SET status='failed', error_code=$2, updated_at=now() WHERE id=$1", matchID, code)
}

func (d *deps) mustExec(query string, args ...any) {
	if _, err := d.db.Exec(query, args...); err != nil {
		log.Printf("db exec failed: %v", err)
	}
}

func mustConnectPostgres() *sql.DB {
	dsn := fmt.Sprintf("host=%s port=%s user=%s password=%s dbname=%s sslmode=disable",
		env("DB_HOST", "postgres"), env("DB_PORT", "5432"),
		env("DB_USERNAME", "clutch"), env("DB_PASSWORD", "secret"), env("DB_DATABASE", "clutchlab"))

	db, err := sql.Open("postgres", dsn)
	if err != nil {
		log.Fatalf("postgres open: %v", err)
	}

	// depends_on doesn't wait for readiness, so poll until postgres accepts us.
	for i := 0; i < 30; i++ {
		if err = db.Ping(); err == nil {
			log.Print("connected to postgres")
			return db
		}
		log.Printf("waiting for postgres: %v", err)
		time.Sleep(2 * time.Second)
	}
	log.Fatalf("postgres unreachable: %v", err)
	return nil
}

func mustConnectRedis(ctx context.Context) *redis.Client {
	rdb := redis.NewClient(&redis.Options{
		Addr: env("REDIS_HOST", "redis") + ":" + env("REDIS_PORT", "6379"),
	})
	for i := 0; i < 30; i++ {
		if err := rdb.Ping(ctx).Err(); err == nil {
			log.Print("connected to redis")
			return rdb
		}
		time.Sleep(2 * time.Second)
	}
	log.Fatal("redis unreachable")
	return nil
}

func mustConnectMinio() *minio.Client {
	endpoint := env("AWS_ENDPOINT", "http://minio:9000")
	u, err := url.Parse(endpoint)
	if err != nil {
		log.Fatalf("bad AWS_ENDPOINT %q: %v", endpoint, err)
	}
	mc, err := minio.New(u.Host, &minio.Options{
		Creds:  credentials.NewStaticV4(env("AWS_ACCESS_KEY_ID", "minioadmin"), env("AWS_SECRET_ACCESS_KEY", "minioadmin"), ""),
		Secure: u.Scheme == "https",
	})
	if err != nil {
		log.Fatalf("minio init: %v", err)
	}
	log.Print("minio client ready")
	return mc
}

func env(key, fallback string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return fallback
}
