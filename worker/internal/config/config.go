package config

import (
	"fmt"
	"os"
	"strconv"
)

// Config is the worker's whole configuration, read from the environment that
// Docker Compose injects (repo-root .env). No config files.
type Config struct {
	DBHost, DBPort, DBUser, DBPass, DBName string
	RedisAddr                              string
	Queue                                  string
	EventsChannel                          string
	MinioEndpoint, MinioKey, MinioSecret   string
	Bucket                                 string
	MeiliHost, MeiliKey                    string
	OtelEndpoint                           string
	// Sandbox limits on the untrusted-demo parse (a .dem is attacker-controlled input
	// fed to a native parser). A crafted file can loop forever or allocate without
	// bound; these cap the blast radius to one job. See parser.Limits.
	ParseTimeoutSeconds int
	ParseMemoryLimitMB  int
}

func Load() Config {
	return Config{
		DBHost:        env("DB_HOST", "postgres"),
		DBPort:        env("DB_PORT", "5432"),
		DBUser:        env("DB_USERNAME", "clutch"),
		DBPass:        env("DB_PASSWORD", "secret"),
		DBName:        env("DB_DATABASE", "clutchlab"),
		RedisAddr:     env("REDIS_HOST", "redis") + ":" + env("REDIS_PORT", "6379"),
		Queue:         env("PARSE_QUEUE", "demo_parse_jobs"),
		EventsChannel: env("EVENTS_CHANNEL", "clutch_events"),
		MinioEndpoint: env("AWS_ENDPOINT", "http://minio:9000"),
		MinioKey:      env("AWS_ACCESS_KEY_ID", "minioadmin"),
		MinioSecret:   env("AWS_SECRET_ACCESS_KEY", "minioadmin"),
		Bucket:        env("AWS_BUCKET", "demos"),
		MeiliHost:     env("MEILI_HOST", "http://meilisearch:7700"),
		MeiliKey:      env("MEILI_MASTER_KEY", ""),
		OtelEndpoint:  env("OTEL_ENDPOINT", "jaeger:4318"),
		// Defaults sized for a long 128-tick match with headroom; tighten in prod.
		ParseTimeoutSeconds: envInt("PARSE_TIMEOUT_SECONDS", 300),
		ParseMemoryLimitMB:  envInt("PARSE_MEMORY_LIMIT_MB", 2048),
	}
}

func (c Config) DSN() string {
	return fmt.Sprintf("host=%s port=%s user=%s password=%s dbname=%s sslmode=disable",
		c.DBHost, c.DBPort, c.DBUser, c.DBPass, c.DBName)
}

func env(key, fallback string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return fallback
}

func envInt(key string, fallback int) int {
	if v := os.Getenv(key); v != "" {
		if n, err := strconv.Atoi(v); err == nil {
			return n
		}
	}
	return fallback
}
