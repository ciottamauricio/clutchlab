package main

import (
	"fmt"
	"io"
	"strings"

	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs"
	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/common"
	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/events"
	"github.com/markus-wa/demoinfocs-golang/v5/pkg/demoinfocs/msg"
)

type PlayerStat struct {
	SteamID   uint64
	Name      string
	TeamSide  string
	Kills     int
	Deaths    int
	Assists   int
	Headshots int
}

type ParseResult struct {
	MapName     string
	ScoreCT     int
	ScoreT      int
	CTName      string
	TName       string
	TotalRounds int
	Players     []*PlayerStat
}

// ParseDemo streams a CS2 demo through demoinfocs and tallies a per-player
// scoreboard. This is the single "analysis pass" the roadmap grows later
// (heatmaps, clutch detection) by adding more event handlers here.
func ParseDemo(r io.Reader) (result *ParseResult, err error) {
	// demoinfocs panics (not just errors) on some corrupt/unsupported demos, so
	// recover here and turn it into a normal error — one bad upload must not take
	// the whole worker down.
	defer func() {
		if rec := recover(); rec != nil {
			result = nil
			err = fmt.Errorf("panic during parse: %v", rec)
		}
	}()

	p := demoinfocs.NewParser(r)
	defer p.Close()

	gs := p.GameState()

	byID := map[uint64]*PlayerStat{}
	ensure := func(pl *common.Player) *PlayerStat {
		if pl == nil || pl.SteamID64 == 0 {
			return nil
		}
		s, ok := byID[pl.SteamID64]
		if !ok {
			s = &PlayerStat{SteamID: pl.SteamID64}
			byID[pl.SteamID64] = s
		}
		if pl.Name != "" {
			s.Name = pl.Name
		}
		return s
	}

	// v5 exposes no public demo-header accessor, so grab the map name off the
	// ServerInfo net-message as it streams by.
	var mapName string
	p.RegisterNetMessageHandler(func(m *msg.CSVCMsg_ServerInfo) {
		mapName = m.GetMapName()
	})

	// Sides swap at the half and everyone is unassigned once the demo ends, so
	// team_side can't be read afterwards. Snapshot each player's side at the start
	// of every live round; the last round wins, which matches the final CT/T clan
	// names below. This also enrolls players who never got a kill or death.
	p.RegisterEventHandler(func(events.RoundFreezetimeEnd) {
		for _, pl := range gs.Participants().Playing() {
			if s := ensure(pl); s != nil {
				if side := teamSide(pl.Team); side != "" {
					s.TeamSide = side
				}
			}
		}
	})

	p.RegisterEventHandler(func(e events.Kill) {
		if s := ensure(e.Killer); s != nil {
			s.Kills++
			if e.IsHeadshot {
				s.Headshots++
			}
		}
		if s := ensure(e.Victim); s != nil {
			s.Deaths++
		}
		if s := ensure(e.Assister); s != nil {
			s.Assists++
		}
	})

	if err := p.ParseToEnd(); err != nil {
		return nil, err
	}

	ct := gs.TeamCounterTerrorists()
	t := gs.TeamTerrorists()

	res := &ParseResult{
		MapName:     cleanMapName(mapName),
		ScoreCT:     ct.Score(),
		ScoreT:      t.Score(),
		CTName:      ct.ClanName(),
		TName:       t.ClanName(),
		TotalRounds: ct.Score() + t.Score(),
	}

	for _, s := range byID {
		res.Players = append(res.Players, s)
	}

	return res, nil
}

// CS2 ServerInfo can report the map as a workshop path (e.g. "workshop/123/de_nuke");
// keep just the map slug for display.
func cleanMapName(name string) string {
	if i := strings.LastIndex(name, "/"); i >= 0 {
		return name[i+1:]
	}
	return name
}

func teamSide(t common.Team) string {
	switch t {
	case common.TeamCounterTerrorists:
		return "CT"
	case common.TeamTerrorists:
		return "T"
	default:
		return ""
	}
}
