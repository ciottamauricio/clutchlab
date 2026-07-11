// Package ws upgrades HTTP requests to websockets, authenticates them against the
// shared DB, and wires each connection into its tactic's room.
package ws

import (
	"context"
	"encoding/json"
	"log"
	"net/http"
	"strconv"
	"strings"
	"time"

	"github.com/gorilla/websocket"

	"clutchlab/realtime/internal/hub"
	"clutchlab/realtime/internal/store"
)

var upgrader = websocket.Upgrader{
	ReadBufferSize:  1024,
	WriteBufferSize: 1024,
	// Behind nginx on a single origin; the token in the query is the real gate.
	CheckOrigin: func(*http.Request) bool { return true },
}

// outMsg is the wire format sent to clients: snapshot/update carry a board,
// presence carries a count. This shape is a contract with the frontend
// (api/docs/domains/tactics.md).
type outMsg struct {
	Type  string          `json:"type"`
	Board json.RawMessage `json:"board,omitempty"`
	Count *int            `json:"count,omitempty"`
}

type Handler struct {
	hub   *hub.Hub
	store *store.Store
}

func NewHandler(h *hub.Hub, s *store.Store) *Handler {
	return &Handler{hub: h, store: s}
}

// ServeHTTP handles GET /realtime/tactics/{id}?token=<sanctum token>.
func (h *Handler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	id, err := strconv.ParseInt(strings.TrimPrefix(r.URL.Path, "/realtime/tactics/"), 10, 64)
	if err != nil {
		http.Error(w, "bad tactic id", http.StatusBadRequest)
		return
	}

	userID, err := h.store.UserIDForToken(r.Context(), r.URL.Query().Get("token"))
	if err != nil {
		http.Error(w, "unauthenticated", http.StatusUnauthorized)
		return
	}

	allowed, err := h.store.TacticAccess(r.Context(), id, userID)
	if err != nil {
		http.Error(w, "not found", http.StatusNotFound)
		return
	}
	if !allowed {
		http.Error(w, "forbidden", http.StatusForbidden)
		return
	}

	conn, err := upgrader.Upgrade(w, r, nil)
	if err != nil {
		return // Upgrade already wrote the error
	}

	client := &hub.Client{Send: make(chan []byte, 16), TacticID: id}
	h.hub.Join(client)
	log.Printf("tactic %d: client joined (%d in room)", id, h.hub.Count(id))

	// Send the current board, then announce presence to the whole room.
	board, _ := h.store.LoadBoard(context.Background(), id)
	if board == "" {
		board = `{"pieces":[]}`
	}
	client.Send <- encode(outMsg{Type: "snapshot", Board: json.RawMessage(board)})
	h.broadcastPresence(id)

	go writePump(conn, client)
	h.readPump(conn, client)
}

func (h *Handler) readPump(conn *websocket.Conn, client *hub.Client) {
	defer func() {
		h.hub.Leave(client)   // out of the room first (under lock)...
		close(client.Send)    // ...so no broadcast can target this channel now
		conn.Close()
		h.broadcastPresence(client.TacticID)
		log.Printf("tactic %d: client left (%d in room)", client.TacticID, h.hub.Count(client.TacticID))
	}()

	conn.SetReadLimit(1 << 20) // 1 MB
	for {
		_, data, err := conn.ReadMessage()
		if err != nil {
			return
		}

		var msg struct {
			Type  string          `json:"type"`
			Board json.RawMessage `json:"board"`
		}
		if err := json.Unmarshal(data, &msg); err != nil || msg.Type != "update" || len(msg.Board) == 0 {
			continue
		}

		if err := h.store.SaveBoard(context.Background(), client.TacticID, string(msg.Board)); err != nil {
			log.Printf("tactic %d: save failed: %v", client.TacticID, err)
		}
		h.hub.Broadcast(client.TacticID, encode(outMsg{Type: "update", Board: msg.Board}), client)
	}
}

func writePump(conn *websocket.Conn, client *hub.Client) {
	ticker := time.NewTicker(30 * time.Second)
	defer func() {
		ticker.Stop()
		conn.Close()
	}()
	for {
		select {
		case msg, ok := <-client.Send:
			if !ok {
				conn.WriteMessage(websocket.CloseMessage, []byte{})
				return
			}
			if err := conn.WriteMessage(websocket.TextMessage, msg); err != nil {
				return
			}
		case <-ticker.C:
			if err := conn.WriteMessage(websocket.PingMessage, nil); err != nil {
				return
			}
		}
	}
}

func (h *Handler) broadcastPresence(tacticID int64) {
	n := h.hub.Count(tacticID)
	h.hub.Broadcast(tacticID, encode(outMsg{Type: "presence", Count: &n}), nil)
}

func encode(m outMsg) []byte {
	out, _ := json.Marshal(m)
	return out
}
