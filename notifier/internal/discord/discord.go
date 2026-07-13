// Package discord delivers a formatted message to the outside world. The Notifier
// interface keeps the transport swappable (and tests offline); the notifier service
// is the only place in the system that turns event codes into human sentences for
// Discord — the same role the React app plays for the browser.
package discord

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"time"
)

type Notifier interface {
	Notify(ctx context.Context, text string) error
}

// Webhook posts to a Discord channel webhook — plain HTTPS out, no bot, no gateway.
type Webhook struct {
	url    string
	client *http.Client
}

func NewWebhook(url string) *Webhook {
	return &Webhook{url: url, client: &http.Client{Timeout: 10 * time.Second}}
}

func (w *Webhook) Notify(ctx context.Context, text string) error {
	body, err := json.Marshal(map[string]string{"content": text})
	if err != nil {
		return err
	}
	req, err := http.NewRequestWithContext(ctx, http.MethodPost, w.url, bytes.NewReader(body))
	if err != nil {
		return err
	}
	req.Header.Set("Content-Type", "application/json")

	resp, err := w.client.Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()
	// Discord returns 204 on success and 429 when rate-limited; anything non-2xx
	// is reported to the caller, which logs and drops (notifications are best-effort).
	if resp.StatusCode < 200 || resp.StatusCode > 299 {
		return fmt.Errorf("discord webhook: %s", resp.Status)
	}
	return nil
}

// Log is the no-webhook fallback: same interface, stdout instead of Discord, so the
// whole event pipeline works end-to-end before a webhook URL exists.
type Log struct{}

func (Log) Notify(_ context.Context, text string) error {
	log.Printf("(log-only) %s", text)
	return nil
}
