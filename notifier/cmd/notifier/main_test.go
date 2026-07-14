package main

import (
	"testing"

	"clutchlab/notifier/internal/sub"
)

// message() is the notifier's whole job as a pure function: event in, Discord text out.
// The table is the spec — including the rule that unknown events are skipped, because
// new event types may appear on the channel before this service learns them.
func TestMessage(t *testing.T) {
	tests := []struct {
		name string
		e    sub.Event
		want string
		ok   bool
	}{
		{
			name: "parsed with demo name",
			e:    sub.Event{Event: "match.parsed", MatchID: 42, Demo: "x.dem", Map: "de_mirage", ScoreCT: 13, ScoreT: 9},
			want: "✅ **x.dem** parsed — de_mirage 13:9",
			ok:   true,
		},
		{
			name: "parsed without demo name falls back to match id",
			e:    sub.Event{Event: "match.parsed", MatchID: 42, Map: "de_nuke", ScoreCT: 13, ScoreT: 0},
			want: "✅ **match #42** parsed — de_nuke 13:0",
			ok:   true,
		},
		{
			name: "failed carries the error code, never prose",
			e:    sub.Event{Event: "match.failed", MatchID: 7, ErrorCode: "parse_failed_corrupt"},
			want: "❌ match #7 failed (parse_failed_corrupt)",
			ok:   true,
		},
		{
			name: "training scheduled renders a viewer-local discord timestamp",
			e: sub.Event{
				Event: "training.scheduled", Title: "A-executes + retakes", Team: "LOLO Clan",
				ScheduledAt: "2026-07-17T21:00:00.000000Z", Tactics: 2, Players: 5,
			},
			want: "📅 **A-executes + retakes** — LOLO Clan · <t:1784322000:F> · 2 tactics, 5 expected",
			ok:   true,
		},
		{
			name: "unknown events are skipped, not errors",
			e:    sub.Event{Event: "team.member_joined", MatchID: 1},
			ok:   false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, ok := message(tt.e)
			if ok != tt.ok {
				t.Fatalf("ok = %v, want %v", ok, tt.ok)
			}
			if ok && got != tt.want {
				t.Errorf("got  %q\nwant %q", got, tt.want)
			}
		})
	}
}
