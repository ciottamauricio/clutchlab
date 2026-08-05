package main

import (
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"os"
	"os/exec"

	"clutchlab/worker/internal/parser"
)

// The subcommand the worker binary re-invokes on itself to run one parse in a throwaway
// process. Kept as a flag rather than a separate binary so there's exactly one artifact
// to build and ship.
const parseChildFlag = "--parse-child"

// Errors the parent raises for an isolated parse, mapped from the child's exit code so
// the caller can still produce the right parse_failed_* status.
var (
	errChildTimeout = errors.New("isolated parse exceeded time limit")
	errChildMemory  = errors.New("isolated parse exceeded memory limit")
	errChildParse   = errors.New("isolated parse failed (corrupt/crashed)")
)

// runParseChild is the child process body: read the demo from stdin, write ParseResult
// JSON to stdout, exit with a code the parent reads. Nothing else may touch stdout.
func runParseChild(limits parser.Limits) int {
	return parser.RunChild(os.Stdin, os.Stdout, limits)
}

// parseIsolated parses the demo in a separate process: the worker re-execs itself in
// --parse-child mode, streams the demo to the child's stdin, and decodes the result from
// its stdout. If the child crashes, is OOM-killed by the OS, or the native parser is
// exploited, the damage is confined to that process — the parent sees a non-zero exit and
// fails the one job. The in-process resource limits still run *inside* the child, so this
// is a second containment layer, not a replacement.
func parseIsolated(ctx context.Context, demo io.Reader, limits parser.Limits) (*parser.ParseResult, error) {
	self, err := os.Executable()
	if err != nil {
		return nil, fmt.Errorf("locate self for isolation: %w", err)
	}

	cmd := exec.CommandContext(ctx, self, parseChildFlag)
	cmd.Stdin = demo

	var stdout, stderr bytes.Buffer
	cmd.Stdout = &stdout
	cmd.Stderr = &stderr

	// Least privilege for the child: a fresh, empty environment. The parse needs no
	// secrets, no DB creds, no S3 keys — only the bytes on stdin — so it inherits none of
	// the worker's environment. (Network/filesystem lockdown is the OS-level next step;
	// this closes the "child can read the worker's secrets" hole today.)
	cmd.Env = []string{}

	runErr := cmd.Run()
	if runErr == nil {
		var res parser.ParseResult
		if err := json.NewDecoder(&stdout).Decode(&res); err != nil {
			return nil, fmt.Errorf("decode isolated result: %w", err)
		}
		return &res, nil
	}

	// Non-zero exit: translate the child's code into the matching error. An exit the OS
	// forced (signal: killed for OOM, or a segfault from an exploit) has no clean code, so
	// it falls through to errChildParse — contained, and reported as a failed parse.
	var exit *exec.ExitError
	if errors.As(runErr, &exit) {
		switch exit.ExitCode() {
		case parser.ExitTimeout:
			return nil, errChildTimeout
		case parser.ExitMemory:
			return nil, errChildMemory
		}
	}
	return nil, fmt.Errorf("%w: %v (stderr: %s)", errChildParse, runErr, stderr.String())
}
