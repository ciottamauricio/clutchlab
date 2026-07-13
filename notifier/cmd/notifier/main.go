package main

import (
	"context"
	"fmt"
	"log"

	"clutchlab/notifier/internal/config"
	"clutchlab/notifier/internal/discord"
	"clutchlab/notifier/internal/sub"
)

func main() {
	log.SetFlags(log.LstdFlags | log.Lmsgprefix)
	log.SetPrefix("[notifier] ")

	ctx := context.Background()
	cfg := config.Load()

	var out discord.Notifier = discord.Log{}
	if cfg.WebhookURL != "" {
		out = discord.NewWebhook(cfg.WebhookURL)
		log.Print("discord webhook configured")
	} else {
		log.Print("no DISCORD_WEBHOOK_URL — running log-only")
	}

	s, err := sub.Connect(ctx, cfg.RedisAddr, cfg.Channel)
	if err != nil {
		log.Fatal(err)
	}
	defer s.Close()

	log.Printf("subscribed to %q", cfg.Channel)
	s.Listen(ctx, func(e sub.Event) {
		text, ok := message(e)
		if !ok {
			log.Printf("event %q ignored (no message for it)", e.Event)
			return
		}
		if err := out.Notify(ctx, text); err != nil {
			log.Printf("notify failed (dropped): %v", err)
			return
		}
		log.Printf("notified: %s", text)
	})
}

// message renders an event as Discord text. Unknown event types are skipped, not
// errors — new events may appear on the channel before this service learns them.
func message(e sub.Event) (string, bool) {
	switch e.Event {
	case "match.parsed":
		name := e.Demo
		if name == "" {
			name = fmt.Sprintf("match #%d", e.MatchID)
		}
		return fmt.Sprintf("✅ **%s** parsed — %s %d:%d", name, e.Map, e.ScoreCT, e.ScoreT), true
	case "match.failed":
		return fmt.Sprintf("❌ match #%d failed (%s)", e.MatchID, e.ErrorCode), true
	default:
		return "", false
	}
}
