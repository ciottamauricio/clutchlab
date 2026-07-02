// Package store is the realtime service's window onto the shared database: it
// validates Sanctum tokens (the same ones Laravel issues) and reads/writes the
// tactic board. Auth-by-shared-DB is the cross-service pattern from the worker.
package store

import (
	"context"
	"crypto/sha256"
	"database/sql"
	"encoding/hex"
	"strings"
	"time"
)

type Store struct {
	db *sql.DB
}

func New(db *sql.DB) *Store {
	return &Store{db: db}
}

// UserIDForToken validates a Sanctum bearer token. The token is "<id>|<plain>";
// personal_access_tokens stores sha256(plain). Returns the owning user id.
func (s *Store) UserIDForToken(ctx context.Context, token string) (int64, error) {
	if token == "" {
		return 0, sql.ErrNoRows
	}
	plain := token
	if i := strings.Index(token, "|"); i >= 0 {
		plain = token[i+1:]
	}
	sum := sha256.Sum256([]byte(plain))
	hash := hex.EncodeToString(sum[:])

	var userID int64
	var expiresAt sql.NullTime
	err := s.db.QueryRowContext(ctx,
		`SELECT tokenable_id, expires_at FROM personal_access_tokens
		 WHERE token = $1 AND tokenable_type = $2`,
		hash, `App\Models\User`).Scan(&userID, &expiresAt)
	if err != nil {
		return 0, err
	}
	if expiresAt.Valid && expiresAt.Time.Before(time.Now()) {
		return 0, sql.ErrNoRows
	}
	return userID, nil
}

func (s *Store) TacticOwnerID(ctx context.Context, tacticID int64) (int64, error) {
	var ownerID int64
	err := s.db.QueryRowContext(ctx, `SELECT user_id FROM tactics WHERE id = $1`, tacticID).Scan(&ownerID)
	return ownerID, err
}

// LoadBoard returns the board JSON (empty string if null/absent).
func (s *Store) LoadBoard(ctx context.Context, tacticID int64) (string, error) {
	var board sql.NullString
	err := s.db.QueryRowContext(ctx, `SELECT board FROM tactics WHERE id = $1`, tacticID).Scan(&board)
	if err != nil {
		return "", err
	}
	if !board.Valid {
		return "", nil
	}
	return board.String, nil
}

func (s *Store) SaveBoard(ctx context.Context, tacticID int64, board string) error {
	_, err := s.db.ExecContext(ctx,
		`UPDATE tactics SET board = $2::json, updated_at = now() WHERE id = $1`,
		tacticID, board)
	return err
}
