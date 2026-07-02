// Package db opens the shared Postgres connection. depends_on doesn't wait for
// readiness, so Connect polls until the server accepts us.
package db

import (
	"database/sql"
	"fmt"
	"time"

	_ "github.com/lib/pq"
)

func Connect(dsn string) (*sql.DB, error) {
	conn, err := sql.Open("postgres", dsn)
	if err != nil {
		return nil, err
	}

	for i := 0; i < 30; i++ {
		if err = conn.Ping(); err == nil {
			return conn, nil
		}
		time.Sleep(2 * time.Second)
	}
	return nil, fmt.Errorf("postgres unreachable: %w", err)
}
