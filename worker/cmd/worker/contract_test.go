package main

import (
	"bytes"
	"encoding/json"
	"os"
	"strings"
	"testing"
)

// Consumer side of the queue contract (contracts/parse_job.json — produced by the
// Laravel api). DisallowUnknownFields makes this fail if the api starts sending a
// field this struct doesn't know: the queue is point-to-point with a same-commit
// rule, so drift on either side should turn a suite red.
func TestJobDecodesTheContractFixture(t *testing.T) {
	raw, err := os.ReadFile("../../../contracts/parse_job.json")
	if err != nil {
		t.Fatalf("contract fixture: %v", err)
	}

	dec := json.NewDecoder(bytes.NewReader(bytes.TrimSpace(raw)))
	dec.DisallowUnknownFields()

	var job Job
	if err := dec.Decode(&job); err != nil {
		t.Fatalf("decode: %v", err)
	}
	if job.MatchID != 123 {
		t.Errorf("match_id = %d, want 123", job.MatchID)
	}
	if job.DemoKey != "demos/abc.dem" {
		t.Errorf("demo_key = %q, want %q", job.DemoKey, "demos/abc.dem")
	}
	if strings.Count(string(raw), "{") != 1 {
		t.Errorf("fixture should be a single flat object")
	}
}
