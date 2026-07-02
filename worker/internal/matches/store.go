// Package matches is the worker's write side of the shared `matches` domain:
// it moves a match through its parsing lifecycle and persists the scoreboard.
package matches

import (
	"database/sql"
	"strconv"

	"clutchlab/worker/internal/parser"
)

type Store struct {
	db *sql.DB
}

func NewStore(db *sql.DB) *Store {
	return &Store{db: db}
}

func (s *Store) SetParsing(id int64) error {
	_, err := s.db.Exec("UPDATE matches SET status='parsing', updated_at=now() WHERE id=$1", id)
	return err
}

func (s *Store) Fail(id int64, code string) error {
	_, err := s.db.Exec("UPDATE matches SET status='failed', error_code=$2, updated_at=now() WHERE id=$1", id, code)
	return err
}

// Save writes the whole result atomically. Delete-then-insert makes redelivery
// of the same job idempotent instead of doubling a player's stats.
func (s *Store) Save(id int64, res *parser.ParseResult) error {
	tx, err := s.db.Begin()
	if err != nil {
		return err
	}
	defer tx.Rollback()

	if _, err := tx.Exec("DELETE FROM match_player_stats WHERE match_id=$1", id); err != nil {
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
		if _, err := stmt.Exec(id, strconv.FormatUint(p.SteamID, 10), p.Name, p.TeamSide,
			p.Kills, p.Deaths, p.Assists, p.Headshots); err != nil {
			return err
		}
	}

	if _, err := tx.Exec(`UPDATE matches SET
		status='parsed', error_code=NULL,
		map_name=$2, score_ct=$3, score_t=$4, ct_name=$5, t_name=$6, total_rounds=$7,
		parsed_at=now(), updated_at=now()
		WHERE id=$1`,
		id, res.MapName, res.ScoreCT, res.ScoreT, res.CTName, res.TName, res.TotalRounds); err != nil {
		return err
	}

	return tx.Commit()
}
