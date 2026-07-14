package sub

import (
	"bytes"
	"encoding/json"
	"os"
	"testing"
)

// Consumer side of the event contract (contracts/match_parsed.json — produced by the
// worker). No DisallowUnknownFields here, deliberately: events are one-to-many and
// additive, so a subscriber must tolerate fields (and whole events) it doesn't know.
func TestEventDecodesTheContractFixture(t *testing.T) {
	raw, err := os.ReadFile("../../../contracts/match_parsed.json")
	if err != nil {
		t.Fatalf("contract fixture: %v", err)
	}

	var e Event
	if err := json.Unmarshal(bytes.TrimSpace(raw), &e); err != nil {
		t.Fatalf("decode: %v", err)
	}

	want := Event{Event: "match.parsed", V: 1, MatchID: 42, Demo: "x.dem", Map: "de_mirage", ScoreCT: 13, ScoreT: 9}
	if e != want {
		t.Errorf("decoded %+v, want %+v", e, want)
	}
}

// Consumer side of the training.scheduled contract (produced by the Laravel api —
// its EventBusContractTest pins the bytes).
func TestTrainingScheduledDecodesTheContractFixture(t *testing.T) {
	raw, err := os.ReadFile("../../../contracts/training_scheduled.json")
	if err != nil {
		t.Fatalf("contract fixture: %v", err)
	}

	var e Event
	if err := json.Unmarshal(bytes.TrimSpace(raw), &e); err != nil {
		t.Fatalf("decode: %v", err)
	}

	want := Event{
		Event: "training.scheduled", V: 1, TrainingID: 7, Team: "LOLO Clan",
		Title: "A-executes + retakes", ScheduledAt: "2026-07-17T21:00:00.000000Z",
		Tactics: 2, Players: 5,
	}
	if e != want {
		t.Errorf("decoded %+v, want %+v", e, want)
	}
}
