package events

import (
	"bytes"
	"encoding/json"
	"os"
	"testing"
)

// Producer side of the event contract (contracts/match_parsed.json — consumed by the
// notifier). This side defines the canonical bytes: exact comparison, so any change to
// the Event struct's fields, order, or omitempty behavior shows up here.
func TestEventMarshalsToTheContractFixture(t *testing.T) {
	raw, err := os.ReadFile("../../../contracts/match_parsed.json")
	if err != nil {
		t.Fatalf("contract fixture: %v", err)
	}

	e := Event{
		Event: "match.parsed", V: 1, MatchID: 42,
		Demo: "x.dem", Map: "de_mirage", ScoreCT: 13, ScoreT: 9,
	}
	got, err := json.Marshal(e)
	if err != nil {
		t.Fatal(err)
	}

	if want := bytes.TrimSpace(raw); !bytes.Equal(got, want) {
		t.Errorf("event bytes drifted from contract\n got: %s\nwant: %s", got, want)
	}
}

// Empty optional fields must stay off the wire — additive-only means consumers can
// rely on absence, not on nulls or zero-value strings.
func TestOptionalFieldsAreOmittedWhenEmpty(t *testing.T) {
	got, _ := json.Marshal(Event{Event: "match.failed", V: 1, MatchID: 7, ErrorCode: "parse_failed_corrupt"})
	for _, banned := range []string{"demo", "map", "traceparent"} {
		if bytes.Contains(got, []byte(`"`+banned+`"`)) {
			t.Errorf("%q should be omitted when empty: %s", banned, got)
		}
	}
}
