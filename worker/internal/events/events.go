// Package events is the publish side of the cross-service event channel: Redis pub/sub
// carrying JSON facts ("this happened"), as opposed to the parse queue's commands ("do
// this"). Publishers don't know their subscribers — the notifier listens today; anything
// may listen tomorrow without this package changing.
//
// Delivery is fire-and-forget: pub/sub reaches only subscribers connected at that
// moment. Acceptable for notifications; the earned upgrade when a gap starts to hurt is
// Redis Streams (or a broker) behind this same interface.
package events

import (
	"context"
	"encoding/json"
	"time"

	"github.com/redis/go-redis/v9"
)

// Event is the cross-language contract shared with the notifier (and any future
// subscriber). Additive changes only; bump V for anything breaking. Keep it in sync
// with notifier/internal/sub — change both sides in the same commit.
type Event struct {
	Event     string `json:"event"` // "match.parsed" | "match.failed"
	V         int    `json:"v"`
	MatchID   int64  `json:"match_id"`
	Demo      string `json:"demo,omitempty"`
	Map       string `json:"map,omitempty"`
	ScoreCT   int    `json:"score_ct"`
	ScoreT    int    `json:"score_t"`
	ErrorCode string `json:"error_code,omitempty"`
}

// Publisher decouples the fact from the transport: swapping pub/sub for Streams or a
// broker is a new implementation here, never a change at the call sites.
type Publisher interface {
	Publish(ctx context.Context, e Event) error
}

type RedisPublisher struct {
	rdb     *redis.Client
	channel string
}

func NewRedis(ctx context.Context, addr, channel string) (*RedisPublisher, error) {
	rdb := redis.NewClient(&redis.Options{Addr: addr})
	for i := 0; i < 30; i++ {
		if err := rdb.Ping(ctx).Err(); err == nil {
			return &RedisPublisher{rdb: rdb, channel: channel}, nil
		}
		time.Sleep(2 * time.Second)
	}
	return nil, redis.ErrClosed
}

func (p *RedisPublisher) Publish(ctx context.Context, e Event) error {
	b, err := json.Marshal(e)
	if err != nil {
		return err
	}
	return p.rdb.Publish(ctx, p.channel, b).Err()
}

func (p *RedisPublisher) Close() error {
	return p.rdb.Close()
}
