package hub

import "testing"

func newClient(tacticID int64, buf int) *Client {
	return &Client{Send: make(chan []byte, buf), TacticID: tacticID}
}

func TestJoinAndLeaveTrackTheRoom(t *testing.T) {
	h := New()
	c := newClient(7, 1)

	h.Join(c)
	if got := h.Count(7); got != 1 {
		t.Fatalf("after join: count = %d, want 1", got)
	}

	h.Leave(c)
	if got := h.Count(7); got != 0 {
		t.Fatalf("after leave: count = %d, want 0", got)
	}
}

func TestRoomsAreIsolatedByTacticID(t *testing.T) {
	h := New()
	a := newClient(1, 1)
	b := newClient(2, 1)
	h.Join(a)
	h.Join(b)

	// A message to room 1 must never reach a client in room 2.
	h.Broadcast(1, []byte("hi"), nil)

	if len(a.Send) != 1 {
		t.Errorf("client in room 1: got %d messages, want 1", len(a.Send))
	}
	if len(b.Send) != 0 {
		t.Errorf("client in room 2: got %d messages, want 0 (isolation broken)", len(b.Send))
	}
}

func TestBroadcastSkipsTheSender(t *testing.T) {
	h := New()
	sender := newClient(3, 1)
	other := newClient(3, 1)
	h.Join(sender)
	h.Join(other)

	// The originator passes itself as `except` so it doesn't echo its own edit.
	h.Broadcast(3, []byte("move"), sender)

	if len(sender.Send) != 0 {
		t.Errorf("sender received its own broadcast (%d), want 0", len(sender.Send))
	}
	if len(other.Send) != 1 {
		t.Errorf("other client: got %d, want 1", len(other.Send))
	}
}

func TestBroadcastSkipsAFullClientInsteadOfBlocking(t *testing.T) {
	h := New()
	slow := newClient(4, 1)
	slow.Send <- []byte("already full") // buffer of 1, now full
	h.Join(slow)

	done := make(chan struct{})
	go func() {
		// Must not block on the full channel — the room stays responsive.
		h.Broadcast(4, []byte("dropped"), nil)
		close(done)
	}()

	<-done
	if len(slow.Send) != 1 {
		t.Errorf("full client buffer = %d, want 1 (the new message should be dropped)", len(slow.Send))
	}
}

func TestEmptyRoomIsCleanedUp(t *testing.T) {
	h := New()
	c := newClient(9, 1)
	h.Join(c)
	h.Leave(c)

	// The last leaver removes the room's map entry (not just empties it), so idle
	// tactics don't leak memory. Count on a missing room is 0 either way, so assert
	// on the internal map directly.
	h.mu.RLock()
	_, exists := h.rooms[9]
	h.mu.RUnlock()
	if exists {
		t.Error("room 9 map entry survived the last leave — memory leak")
	}
}

func TestLeaveOnUnknownRoomIsSafe(t *testing.T) {
	h := New()
	// Leaving a room that was never joined must not panic.
	h.Leave(newClient(99, 1))
}
