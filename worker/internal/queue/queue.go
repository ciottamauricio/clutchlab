// Package queue is the consumer side of the cross-language parse queue: a plain
// Redis list of JSON jobs shared with the Laravel side (docs/ARCHITECTURE.md).
package queue

import (
	"context"
	"time"

	"github.com/redis/go-redis/v9"
)

type Consumer struct {
	rdb  *redis.Client
	list string
}

func Connect(ctx context.Context, addr, list string) (*Consumer, error) {
	rdb := redis.NewClient(&redis.Options{Addr: addr})
	for i := 0; i < 30; i++ {
		if err := rdb.Ping(ctx).Err(); err == nil {
			return &Consumer{rdb: rdb, list: list}, nil
		}
		time.Sleep(2 * time.Second)
	}
	return nil, redis.ErrClosed
}

// Next blocks until a job is available (BLPOP), returning the raw JSON payload.
func (c *Consumer) Next(ctx context.Context) (string, error) {
	vals, err := c.rdb.BLPop(ctx, 0, c.list).Result()
	if err != nil {
		return "", err
	}
	return vals[1], nil
}

func (c *Consumer) Close() error {
	return c.rdb.Close()
}
