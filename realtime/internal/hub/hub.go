// Package hub keeps the in-memory rooms: one per tactic id, each a set of
// connected clients. This is the "stateful, concurrent" workload that justifies a
// separate service from the batch worker (docs/ARCHITECTURE.md).
package hub

import "sync"

type Client struct {
	Send     chan []byte
	TacticID int64
}

type Hub struct {
	mu    sync.RWMutex
	rooms map[int64]map[*Client]bool
}

func New() *Hub {
	return &Hub{rooms: make(map[int64]map[*Client]bool)}
}

func (h *Hub) Join(c *Client) {
	h.mu.Lock()
	defer h.mu.Unlock()
	room := h.rooms[c.TacticID]
	if room == nil {
		room = make(map[*Client]bool)
		h.rooms[c.TacticID] = room
	}
	room[c] = true
}

func (h *Hub) Leave(c *Client) {
	h.mu.Lock()
	defer h.mu.Unlock()
	room := h.rooms[c.TacticID]
	if room == nil {
		return
	}
	delete(room, c)
	if len(room) == 0 {
		delete(h.rooms, c.TacticID)
	}
}

// Broadcast sends msg to everyone in the tactic's room except `except` (may be nil).
// Slow clients whose buffer is full are skipped rather than blocking the room.
func (h *Hub) Broadcast(tacticID int64, msg []byte, except *Client) {
	h.mu.RLock()
	defer h.mu.RUnlock()
	for c := range h.rooms[tacticID] {
		if c == except {
			continue
		}
		select {
		case c.Send <- msg:
		default:
		}
	}
}

func (h *Hub) Count(tacticID int64) int {
	h.mu.RLock()
	defer h.mu.RUnlock()
	return len(h.rooms[tacticID])
}
