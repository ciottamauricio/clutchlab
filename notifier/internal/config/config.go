package config

import "os"

// Config comes from the environment Docker Compose injects (repo-root .env).
// An empty WebhookURL is valid: the notifier runs in log-only mode, so the
// event flow is testable without a Discord server.
type Config struct {
	RedisAddr  string
	Channel    string
	WebhookURL string
}

func Load() Config {
	return Config{
		RedisAddr:  env("REDIS_HOST", "redis") + ":" + env("REDIS_PORT", "6379"),
		Channel:    env("EVENTS_CHANNEL", "clutch_events"),
		WebhookURL: os.Getenv("DISCORD_WEBHOOK_URL"),
	}
}

func env(key, fallback string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return fallback
}
