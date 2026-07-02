package config

import (
	"fmt"
	"os"
)

// Config for the realtime service, read from the environment Compose injects.
// (Duplicated shape across Go services on purpose — see worker/docs/ENGINEERING.md.)
type Config struct {
	DBHost, DBPort, DBUser, DBPass, DBName string
	HTTPAddr                               string
}

func Load() Config {
	return Config{
		DBHost:   env("DB_HOST", "postgres"),
		DBPort:   env("DB_PORT", "5432"),
		DBUser:   env("DB_USERNAME", "clutch"),
		DBPass:   env("DB_PASSWORD", "secret"),
		DBName:   env("DB_DATABASE", "clutchlab"),
		HTTPAddr: ":" + env("REALTIME_PORT", "8090"),
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
