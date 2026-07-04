// Package search is the worker's write side of the search read model. Callers depend
// on Indexer; MeiliIndexer is the Meilisearch implementation. Swapping engines later
// means a new implementation here, nothing else changes (api/docs/domains/search.md).
package search

import (
	"context"
	"fmt"

	"github.com/meilisearch/meilisearch-go"
)

// KillDoc / RoundDoc are the read-model document shapes — a contract with the api
// (reader). The `id` is the Postgres row id, used as the Meilisearch primary key.
type KillDoc struct {
	ID         int64  `json:"id"`
	MatchID    int64  `json:"match_id"`
	OwnerID    int64  `json:"owner_id"`
	Map        string `json:"map"`
	Round         int    `json:"round"`
	KillerName    string `json:"killer_name"`
	KillerSteamID string `json:"killer_steam_id"`
	VictimName    string `json:"victim_name"`
	Weapon     string         `json:"weapon"`
	Headshot   bool           `json:"headshot"`
	Opening    bool           `json:"opening"`
	Side       string         `json:"side"`
	KillerTeam string         `json:"killer_team"`
	Clutch     int            `json:"clutch"`
	Hitgroups  map[string]int `json:"hitgroups,omitempty"`
	Tick       int            `json:"tick"`
	TickRate   float64        `json:"tick_rate"`
	Demo       string         `json:"demo"`
}

type RoundDoc struct {
	ID      int64  `json:"id"`
	MatchID int64  `json:"match_id"`
	OwnerID int64  `json:"owner_id"`
	Map     string `json:"map"`
	Round   int    `json:"round"`
	Winner  string `json:"winner"`
	Reason  string `json:"reason"`
	CTAlive int    `json:"ct_alive"`
	TAlive  int    `json:"t_alive"`
	CTBuy   string `json:"ct_buy"`
	TBuy    string `json:"t_buy"`
}

type Indexer interface {
	IndexKills(ctx context.Context, docs []KillDoc) error
	IndexRounds(ctx context.Context, docs []RoundDoc) error
	DeleteMatch(ctx context.Context, matchID int64) error
}

type MeiliIndexer struct {
	client meilisearch.ServiceManager
}

func NewMeili(host, key string) *MeiliIndexer {
	return &MeiliIndexer{client: meilisearch.New(host, meilisearch.WithAPIKey(key))}
}

func (m *MeiliIndexer) IndexKills(_ context.Context, docs []KillDoc) error {
	if len(docs) == 0 {
		return nil
	}
	_, err := m.client.Index("kills").AddDocuments(docs, primaryKey())
	return err
}

func (m *MeiliIndexer) IndexRounds(_ context.Context, docs []RoundDoc) error {
	if len(docs) == 0 {
		return nil
	}
	_, err := m.client.Index("rounds").AddDocuments(docs, primaryKey())
	return err
}

// DeleteMatch removes a match's documents so a re-parse doesn't leave stale hits.
func (m *MeiliIndexer) DeleteMatch(_ context.Context, matchID int64) error {
	filter := fmt.Sprintf("match_id = %d", matchID)
	if _, err := m.client.Index("kills").DeleteDocumentsByFilter(filter, nil); err != nil {
		return err
	}
	if _, err := m.client.Index("rounds").DeleteDocumentsByFilter(filter, nil); err != nil {
		return err
	}
	return nil
}

func primaryKey() *meilisearch.DocumentOptions {
	pk := "id"
	return &meilisearch.DocumentOptions{PrimaryKey: &pk}
}
