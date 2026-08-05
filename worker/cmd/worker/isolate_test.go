package main

import (
	"testing"

	"clutchlab/worker/internal/parser"
)

// parseFailCode must produce the same status whether a limit was hit in-process or in the
// isolated child — a timeout is a timeout regardless of where it happened. A crashed or
// OOM-killed child (errChildParse) is contained and reported as a corrupt parse.
func TestParseFailCodeMapping(t *testing.T) {
	cases := map[error]string{
		parser.ErrParseTimeout: "parse_failed_timeout",
		parser.ErrParseMemory:  "parse_failed_memory",
		errChildTimeout:        "parse_failed_timeout",
		errChildMemory:         "parse_failed_memory",
		errChildParse:          "parse_failed_corrupt",
	}
	for err, want := range cases {
		if got := parseFailCode(err); got != want {
			t.Errorf("parseFailCode(%v) = %q, want %q", err, got, want)
		}
	}
}
