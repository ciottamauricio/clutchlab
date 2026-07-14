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
