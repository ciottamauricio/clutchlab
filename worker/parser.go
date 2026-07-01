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

	byID := map[uint64]*PlayerStat{}
	stat := func(pl *common.Player) *PlayerStat {
		if pl == nil || pl.SteamID64 == 0 {
			return nil
		}
		s, ok := byID[pl.SteamID64]
		if !ok {
			s = &PlayerStat{SteamID: pl.SteamID64}
			byID[pl.SteamID64] = s
		}
		s.Name = pl.Name
		return s
	}

	// v5 exposes no public demo-header accessor, so grab the map name off the
	// ServerInfo net-message as it streams by.
	var mapName string
	p.RegisterNetMessageHandler(func(m *msg.CSVCMsg_ServerInfo) {
		mapName = m.GetMapName()
	})

	p.RegisterEventHandler(func(e events.Kill) {
		if s := stat(e.Killer); s != nil {
			s.Kills++
			if e.IsHeadshot {
				s.Headshots++
			}
		}
		if s := stat(e.Victim); s != nil {
			s.Deaths++
		}
		if s := stat(e.Assister); s != nil {
			s.Assists++
		}
	})

	if err := p.ParseToEnd(); err != nil {
		return nil, err
	}

	gs := p.GameState()
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

	for _, pl := range gs.Participants().Playing() {
		if s, ok := byID[pl.SteamID64]; ok {
			s.TeamSide = teamSide(pl.Team)
		}
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
