// Package sub is the subscribe side of the cross-service event channel (Redis pub/sub,
// JSON events). The Event shape is the cross-language contract shared with the worker
// (worker/internal/events) — change both sides in the same commit.
//
// Pub/sub delivery is fire-and-forget: events published while this service is down are
// gone. Accepted for notifications; documented in docs/ARCHITECTURE.md.
package sub

import (
	"context"
	"encoding/json"
	"log"
	"time"

	"github.com/redis/go-redis/v9"
)

type Event struct {
	Event     string `json:"event"` // "match.parsed" | "match.failed"
	V         int    `json:"v"`
	MatchID   int64  `json:"match_id"`
	Demo      string `json:"demo"`
	Map       string `json:"map"`
	ScoreCT   int    `json:"score_ct"`
	ScoreT    int    `json:"score_t"`
	ErrorCode string `json:"error_code"`
}

type Subscriber struct {
	rdb     *redis.Client
	channel string
}

func Connect(ctx context.Context, addr, channel string) (*Subscriber, error) {
	rdb := redis.NewClient(&redis.Options{Addr: addr})
	for i := 0; i < 30; i++ {
		if err := rdb.Ping(ctx).Err(); err == nil {
			return &Subscriber{rdb: rdb, channel: channel}, nil
		}
		time.Sleep(2 * time.Second)
	}
	return nil, redis.ErrClosed
}

// Listen delivers each decoded event to handle, forever. go-redis reconnects the
// subscription itself; undecodable payloads are logged and dropped, never fatal.
func (s *Subscriber) Listen(ctx context.Context, handle func(Event)) {
	sub := s.rdb.Subscribe(ctx, s.channel)
	defer sub.Close()

	for msg := range sub.Channel() {
		var e Event
		if err := json.Unmarshal([]byte(msg.Payload), &e); err != nil {
			log.Printf("undecodable event dropped: %v (%s)", err, msg.Payload)
			continue
		}
		handle(e)
	}
}

func (s *Subscriber) Close() error {
	return s.rdb.Close()
}
