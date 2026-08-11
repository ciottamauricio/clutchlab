// Package matches is the worker's write side of the shared `matches` domain:
// it moves a match through its parsing lifecycle and persists the scoreboard
// and the canonical kill/round events that feed search.
package matches

import (
	"database/sql"
	"encoding/json"
	"strconv"

	"clutchlab/worker/internal/parser"
	"clutchlab/worker/internal/search"
)

type Store struct {
	db *sql.DB
}

func NewStore(db *sql.DB) *Store {
	return &Store{db: db}
}

// SaveStats writes the scoreboard into analytics.match_player_stats. It no longer touches
// `matches` (that's the api's table since the DB split — the api applies the parsed summary
// from the match.parsed event). Delete-then-insert keeps redelivery idempotent.
func (s *Store) SaveStats(id int64, res *parser.ParseResult) error {
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

	return tx.Commit()
}

// SaveEvents persists the canonical kill/round events (source of truth) idempotently
// and returns them as read-model documents (with their new row ids) for indexing. owner
// and demoName now come from the job (the api knows both at enqueue) rather than a
// `matches` lookup — the worker has no access to that table anymore.
func (s *Store) SaveEvents(matchID, ownerID int64, demoName string, res *parser.ParseResult) ([]search.KillDoc, []search.RoundDoc, error) {
	tx, err := s.db.Begin()
	if err != nil {
		return nil, nil, err
	}
	defer tx.Rollback()

	if _, err := tx.Exec("DELETE FROM kill_events WHERE match_id=$1", matchID); err != nil {
		return nil, nil, err
	}
	if _, err := tx.Exec("DELETE FROM round_events WHERE match_id=$1", matchID); err != nil {
		return nil, nil, err
	}

	owner := ownerArg(ownerID)

	// demoName (denormalized into each kill doc so a search hit can build its own "watch
	// in game" command) is passed in from the job now — the worker can't read `matches`.

	killStmt, err := tx.Prepare(`INSERT INTO kill_events
		(match_id, owner_id, map, round, killer_steam_id, killer_name, victim_steam_id, victim_name, assister_name, weapon, headshot, opening, side, killer_team, clutch, tick, victim_x, victim_y, hitgroups)
		VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14,$15,$16,$17,$18,$19) RETURNING id`)
	if err != nil {
		return nil, nil, err
	}
	defer killStmt.Close()

	var kills []search.KillDoc
	for _, k := range res.Kills {
		var id int64
		if err := killStmt.QueryRow(matchID, owner, res.MapName, k.Round,
			steamStr(k.KillerSteamID), k.KillerName, steamStr(k.VictimSteamID), k.VictimName,
			k.AssisterName, k.Weapon, k.Headshot, k.Opening, k.Side, k.KillerTeam, k.Clutch, k.Tick,
			nullPos(k.VictimX), nullPos(k.VictimY), hitgroupsJSON(k.Hitgroups)).Scan(&id); err != nil {
			return nil, nil, err
		}
		kills = append(kills, search.KillDoc{
			ID: id, MatchID: matchID, OwnerID: ownerID, Map: res.MapName, Round: k.Round,
			KillerName: k.KillerName, KillerSteamID: steamStr(k.KillerSteamID), VictimName: k.VictimName, Weapon: k.Weapon,
			Headshot: k.Headshot, Opening: k.Opening, Side: k.Side, KillerTeam: k.KillerTeam, Clutch: k.Clutch,
			Hitgroups: k.Hitgroups, Tick: k.Tick, TickRate: res.TickRate, Demo: demoName,
		})
	}

	roundStmt, err := tx.Prepare(`INSERT INTO round_events
		(match_id, owner_id, map, round, winner, reason, ct_alive, t_alive, ct_buy, t_buy)
		VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10) RETURNING id`)
	if err != nil {
		return nil, nil, err
	}
	defer roundStmt.Close()

	var rounds []search.RoundDoc
	for _, r := range res.Rounds {
		var id int64
		if err := roundStmt.QueryRow(matchID, owner, res.MapName, r.Round,
			r.Winner, r.Reason, r.CTAlive, r.TAlive, r.CTBuy, r.TBuy).Scan(&id); err != nil {
			return nil, nil, err
		}
		rounds = append(rounds, search.RoundDoc{
			ID: id, MatchID: matchID, OwnerID: ownerID, Map: res.MapName, Round: r.Round,
			Winner: r.Winner, Reason: r.Reason, CTAlive: r.CTAlive, TAlive: r.TAlive,
			CTBuy: r.CTBuy, TBuy: r.TBuy,
		})
	}

	if err := tx.Commit(); err != nil {
		return nil, nil, err
	}
	return kills, rounds, nil
}

// nullPos stores NULL for a missing position (both coords 0) so the heatmap can skip it,
// rather than pinning the point to the map origin.
func nullPos(v float64) any {
	if v == 0 {
		return nil
	}
	return v
}

// hitgroupsJSON serializes the body-zone damage map for the jsonb column, or NULL when
// there were no tracked hits (e.g. a knife kill or bomb death).
func hitgroupsJSON(hg map[string]int) any {
	if len(hg) == 0 {
		return nil
	}
	b, err := json.Marshal(hg)
	if err != nil {
		return nil
	}
	return string(b)
}

func steamStr(id uint64) string {
	if id == 0 {
		return ""
	}
	return strconv.FormatUint(id, 10)
}

func ownerArg(ownerID int64) any {
	if ownerID <= 0 {
		return nil
	}
	return ownerID
}
