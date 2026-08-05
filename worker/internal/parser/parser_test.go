package parser

import (
	"bytes"
	"context"
	"errors"
	"testing"
	"time"
)

// A demo stream fed under an already-cancelled deadline must stop at the guard with
// ErrParseTimeout rather than grinding through frames — the sandbox's time cap. We don't
// need a valid .dem: the guard checks the context before the first ParseNextFrame, and
// even if it didn't, an empty stream can't outrun an expired deadline.
func TestParseHonorsTimeout(t *testing.T) {
	ctx, cancel := context.WithCancel(context.Background())
	cancel() // already past the deadline

	_, err := ParseDemoWithLimits(ctx, bytes.NewReader(nil), Limits{Timeout: time.Hour})

	if !errors.Is(err, ErrParseTimeout) {
		t.Fatalf("want ErrParseTimeout, got %v", err)
	}
}

// A zero-value Limits disables both caps — the no-sandbox path used by trusted callers.
// An empty reader is not a valid demo, so it errors, but it must NOT be a limit error.
func TestZeroLimitsDisableTheSandbox(t *testing.T) {
	_, err := ParseDemoWithLimits(context.Background(), bytes.NewReader(nil), Limits{})

	if errors.Is(err, ErrParseTimeout) || errors.Is(err, ErrParseMemory) {
		t.Fatalf("zero limits should not trip a limit error, got %v", err)
	}
}
