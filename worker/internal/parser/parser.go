// Package parser turns a CS2 demo stream into a per-player scoreboard plus the
// kill and round events that feed the search read model. This is the single
// "analysis pass" the roadmap grows by adding more event handlers here.
package parser

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

type KillEvent struct {
	Round         int
	KillerSteamID uint64
	KillerName    string
	VictimSteamID uint64
	VictimName    string
	AssisterName  string
	Weapon        string
	Headshot      bool
	Opening       bool
	Side          string
	KillerTeam    string
	Tick          int
	// Clutch is the size of the 1vN this kill was part of (the killer was the last player
	// alive on their team). 0 means it wasn't a clutch kill.
	Clutch  int
	VictimX float64
	VictimY float64
	// Health damage the killer dealt to the victim this round, by body zone
	// (head/chest/stomach/arms/legs) — the "where shots landed" hitgroup map.
	Hitgroups map[string]int
}

type RoundEvent struct {
	Round   int
	Winner  string
	Reason  string
	CTAlive int
	TAlive  int
	CTBuy   string
	TBuy    string
}

type ParseResult struct {
	MapName     string
	ScoreCT     int
	ScoreT      int
	CTName      string
	TName       string
	TotalRounds int
	TickRate    float64
	// DurationSeconds spans actual play (first round start to last round end) —
	// warmup and any post-game time are excluded.
	DurationSeconds float64
	Players         []*PlayerStat
	Kills           []KillEvent
	Rounds          []RoundEvent
}

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
	res := &ParseResult{}

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

	// Round bookkeeping. Warmup rounds are skipped so counts and events are real.
	currentRound := 0
	openingSeen := false
	var ctBuy, tBuy string
	// Ticks spanning actual play, for DurationSeconds — first round's freezetime-end to the
	// last round's end, so warmup and any post-game time don't inflate the duration.
	var matchStartTick, matchEndTick int

	// Clutch tracking: who is alive per side this round, and — once a side is first cut to
	// a single survivor facing live enemies — that clutcher and the 1vN size, so their
	// subsequent kills can be tagged as clutch kills.
	aliveCT := map[uint64]bool{}
	aliveT := map[uint64]bool{}
	var clutcher uint64
	var clutchN int
	clutchRecorded := false

	// Health damage per (attacker, victim, round) by body zone, so each kill can carry
	// the hitgroup breakdown of the engagement that produced it.
	hurts := map[hurtKey]map[string]int{}
	p.RegisterEventHandler(func(e events.PlayerHurt) {
		if gs.IsWarmupPeriod() || currentRound == 0 {
			return
		}
		a, v := steamID(e.Attacker), steamID(e.Player)
		if a == 0 || v == 0 || a == v {
			return
		}
		zone := hitZone(e.HitGroup)
		if zone == "" {
			return
		}
		k := hurtKey{attacker: a, victim: v, round: currentRound}
		if hurts[k] == nil {
			hurts[k] = map[string]int{}
		}
		hurts[k][zone] += e.HealthDamage
	})

	p.RegisterEventHandler(func(events.RoundFreezetimeEnd) {
		if gs.IsWarmupPeriod() {
			return
		}
		currentRound++
		openingSeen = false
		ct := gs.TeamCounterTerrorists()
		t := gs.TeamTerrorists()
		ctBuy = classifyBuy(ct.FreezeTimeEndEquipmentValue())
		tBuy = classifyBuy(t.FreezeTimeEndEquipmentValue())
		if currentRound == 1 {
			matchStartTick = gs.IngameTick()
		}

		// Reset clutch tracking and seed the alive rosters for the new round.
		aliveCT = map[uint64]bool{}
		aliveT = map[uint64]bool{}
		clutcher = 0
		clutchN = 0
		clutchRecorded = false

		// Sides swap at the half and everyone is unassigned once the demo ends, so
		// snapshot team_side each round; the last live round wins.
		for _, pl := range gs.Participants().Playing() {
			if s := ensure(pl); s != nil {
				if side := teamSide(pl.Team); side != "" {
					s.TeamSide = side
				}
			}
			switch pl.Team {
			case common.TeamCounterTerrorists:
				aliveCT[pl.SteamID64] = true
			case common.TeamTerrorists:
				aliveT[pl.SteamID64] = true
			}
		}
	})

	p.RegisterEventHandler(func(e events.Kill) {
		if gs.IsWarmupPeriod() {
			return
		}
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

		if currentRound == 0 {
			return
		}
		opening := !openingSeen
		openingSeen = true
		vx, vy := victimPos(e.Victim)

		// Tag before updating the roster: a kill is a clutch kill if the clutch is already
		// under way and the clutcher fragged an enemy (not a suicide / world death). This is
		// provisional — kills from a *lost* clutch are dropped in a post-pass below.
		clutch := 0
		if clutchRecorded && steamID(e.Killer) == clutcher && steamID(e.Victim) != clutcher {
			clutch = clutchN
		}

		res.Kills = append(res.Kills, KillEvent{
			Round:         currentRound,
			KillerSteamID: steamID(e.Killer),
			KillerName:    name(e.Killer),
			VictimSteamID: steamID(e.Victim),
			VictimName:    name(e.Victim),
			AssisterName:  name(e.Assister),
			Weapon:        weaponName(e.Weapon),
			Headshot:      e.IsHeadshot,
			Opening:       opening,
			Side:          teamSide(killerTeam(e.Killer)),
			Tick:          gs.IngameTick(),
			Clutch:        clutch,
			VictimX:       vx,
			VictimY:       vy,
		})

		// Remove the victim, then start a clutch the first time a side is cut to one
		// survivor while the other side still has players.
		if vid := steamID(e.Victim); vid != 0 {
			delete(aliveCT, vid)
			delete(aliveT, vid)
		}
		if !clutchRecorded {
			ctN, tN := len(aliveCT), len(aliveT)
			if ctN == 1 && tN >= 1 {
				clutcher, clutchN, clutchRecorded = onlyKey(aliveCT), tN, true
			} else if tN == 1 && ctN >= 1 {
				clutcher, clutchN, clutchRecorded = onlyKey(aliveT), ctN, true
			}
		}
	})

	p.RegisterEventHandler(func(e events.RoundEnd) {
		if gs.IsWarmupPeriod() || currentRound == 0 {
			return
		}
		matchEndTick = gs.IngameTick()
		ctAlive, tAlive := 0, 0
		for _, pl := range gs.Participants().Playing() {
			if !pl.IsAlive() {
				continue
			}
			switch pl.Team {
			case common.TeamCounterTerrorists:
				ctAlive++
			case common.TeamTerrorists:
				tAlive++
			}
		}
		res.Rounds = append(res.Rounds, RoundEvent{
			Round:   currentRound,
			Winner:  teamSide(e.Winner),
			Reason:  reasonString(e.Reason),
			CTAlive: ctAlive,
			TAlive:  tAlive,
			CTBuy:   ctBuy,
			TBuy:    tBuy,
		})
	})

	if err := p.ParseToEnd(); err != nil {
		return nil, err
	}

	ct := gs.TeamCounterTerrorists()
	t := gs.TeamTerrorists()
	res.MapName = cleanMapName(mapName)
	res.ScoreCT = ct.Score()
	res.ScoreT = t.Score()
	res.CTName = ct.ClanName()
	res.TName = t.ClanName()
	res.TotalRounds = ct.Score() + t.Score()
	res.TickRate = p.TickRate()
	if matchEndTick > matchStartTick && res.TickRate > 0 {
		res.DurationSeconds = float64(matchEndTick-matchStartTick) / res.TickRate
	}
	for _, s := range byID {
		res.Players = append(res.Players, s)
	}

	// A clutch only counts if it was won. Decide that here, after parsing, rather than at
	// RoundEnd: demos fire round restarts / repeated freezetime events that desync any
	// per-round index bookkeeping. A clutch kill's own Side is the clutcher's side, so if
	// it doesn't match that round's winner the 1vN was lost — drop the tag.
	winnerByRound := make(map[int]string, len(res.Rounds))
	for _, r := range res.Rounds {
		winnerByRound[r.Round] = r.Winner
	}

	// Attribute each kill to the killer's team. A team keeps its roster across the
	// half-time side swap, so a player's final team_side is a stable team id for the
	// whole match — unlike the per-kill Side, which flips at the half.
	for i := range res.Kills {
		if s, ok := byID[res.Kills[i].KillerSteamID]; ok {
			res.Kills[i].KillerTeam = s.TeamSide
		}
		k := hurtKey{attacker: res.Kills[i].KillerSteamID, victim: res.Kills[i].VictimSteamID, round: res.Kills[i].Round}
		if hg := hurts[k]; len(hg) > 0 {
			res.Kills[i].Hitgroups = hg
		}
		if res.Kills[i].Clutch > 0 && res.Kills[i].Side != winnerByRound[res.Kills[i].Round] {
			res.Kills[i].Clutch = 0
		}
	}

	return res, nil
}

type hurtKey struct {
	attacker, victim uint64
	round            int
}

// hitZone collapses demoinfocs hit groups into the five body zones the UI shows.
func hitZone(g events.HitGroup) string {
	switch g {
	case events.HitGroupHead, events.HitGroupNeck:
		return "head"
	case events.HitGroupChest:
		return "chest"
	case events.HitGroupStomach:
		return "stomach"
	case events.HitGroupLeftArm, events.HitGroupRightArm:
		return "arms"
	case events.HitGroupLeftLeg, events.HitGroupRightLeg:
		return "legs"
	default:
		return ""
	}
}

func steamID(pl *common.Player) uint64 {
	if pl == nil {
		return 0
	}
	return pl.SteamID64
}

func name(pl *common.Player) string {
	if pl == nil {
		return ""
	}
	return pl.Name
}

func killerTeam(pl *common.Player) common.Team {
	if pl == nil {
		return common.TeamUnassigned
	}
	return pl.Team
}

// onlyKey returns the single key of a one-element set (the lone survivor).
func onlyKey(m map[uint64]bool) uint64 {
	for k := range m {
		return k
	}
	return 0
}

// victimPos is the victim's world X/Y at death. Raw coordinates only — mapping them onto a
// radar image is a render concern the frontend owns (api/docs/domains/heatmap.md).
func victimPos(pl *common.Player) (float64, float64) {
	if pl == nil {
		return 0, 0
	}
	p := pl.Position()
	return p.X, p.Y
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

func reasonString(r events.RoundEndReason) string {
	switch r {
	case events.RoundEndReasonTargetBombed:
		return "bomb_exploded"
	case events.RoundEndReasonBombDefused:
		return "bomb_defused"
	case events.RoundEndReasonCTWin, events.RoundEndReasonTerroristsWin:
		return "elimination"
	case events.RoundEndReasonTargetSaved:
		return "time"
	default:
		return "other"
	}
}

// classifyBuy is a rough heuristic on a team's freeze-time equipment value.
func classifyBuy(value int) string {
	switch {
	case value < 5000:
		return "eco"
	case value < 18000:
		return "force"
	default:
		return "full"
	}
}

func weaponName(eq *common.Equipment) string {
	if eq == nil {
		return ""
	}
	var b strings.Builder
	for _, r := range strings.ToLower(eq.String()) {
		if (r >= 'a' && r <= 'z') || (r >= '0' && r <= '9') {
			b.WriteRune(r)
		}
	}
	return b.String()
}
