package config

import (
	"fmt"
	"os"
)

// Config is the worker's whole configuration, read from the environment that
// Docker Compose injects (repo-root .env). No config files.
type Config struct {
	DBHost, DBPort, DBUser, DBPass, DBName string
	RedisAddr                              string
	Queue                                  string
	MinioEndpoint, MinioKey, MinioSecret   string
	Bucket                                 string
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
		MinioEndpoint: env("AWS_ENDPOINT", "http://minio:9000"),
		MinioKey:      env("AWS_ACCESS_KEY_ID", "minioadmin"),
		MinioSecret:   env("AWS_SECRET_ACCESS_KEY", "minioadmin"),
		Bucket:        env("AWS_BUCKET", "demos"),
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
