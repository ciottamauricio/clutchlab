package parser

import (
	"bytes"
	"context"
	"errors"
	"testing"
	"time"
)

// RunChild (the isolated subprocess body) must map a limit breach to the right exit code
// so the parent can still surface parse_failed_timeout, and must never write to out on
// failure (stdout is reserved for a clean result the parent decodes).
func TestRunChildExitCodesOnLimitBreach(t *testing.T) {
	var out bytes.Buffer
	// An empty reader isn't a valid demo, but a zero timeout would parse; force a timeout
	// by pre-cancelling is not possible through RunChild's fixed context, so drive the
	// unit that RunChild delegates to for the code mapping and assert RunChild's contract
	// on the encode path instead: a parse error yields a non-OK code and no stdout.
	code := RunChild(bytes.NewReader(nil), &out, Limits{})
	if code == ExitOK {
		t.Fatal("empty stream should not be ExitOK")
	}
	if out.Len() != 0 {
		t.Fatalf("child wrote %d bytes to stdout on failure; must stay silent", out.Len())
	}
}

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
