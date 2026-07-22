import { useCallback, useEffect, useRef, useState } from 'react'
import { api, tokenStore } from '../../lib/api'

export function useTactics() {
  const [tactics, setTactics] = useState([])
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(true)

  const refresh = useCallback(async () => {
    try {
      setTactics(await api.get('/tactics'))
      setError(null)
    } catch (e) {
      setError(e.code ?? 'error.unknown')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    refresh()
  }, [refresh])

  return { tactics, error, loading, refresh }
}

export const createTactic = (name, map = null, teamId = null) =>
  api.post('/tactics', { name, map, team_id: teamId })
export const deleteTactic = (id) => api.delete(`/tactics/${id}`)

const updateTacticTeam = (id, teamId) => api.patch(`/tactics/${id}`, { team_id: teamId })

// Moves a tactic between private and a team (owner only server-side).
export function useUpdateTacticTeam(onUpdated) {
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState(null)

  const setTeam = useCallback(async (id, teamId) => {
    setSaving(true)
    setError(null)
    try {
      const tactic = await updateTacticTeam(id, teamId)
      onUpdated?.(tactic)
      return tactic
    } catch (e) {
      setError(e.code ?? 'error.unknown')
      throw e
    } finally {
      setSaving(false)
    }
  }, [onUpdated])

  return { setTeam, saving, error }
}

// Opens the websocket to the realtime service for one tactic. Returns the live
// board, presence count, and an update() that applies locally and broadcasts.
// The message shape matches the realtime service (api/docs/domains/tactics.md).
export function useTacticBoard(tacticId, canEdit = true) {
  const [board, setBoard] = useState({ pieces: [] })
  const [presence, setPresence] = useState(0)
  const [connected, setConnected] = useState(false)
  const wsRef = useRef(null)
  const lastSent = useRef(0)

  useEffect(() => {
    if (!tacticId) return undefined

    const proto = window.location.protocol === 'https:' ? 'wss' : 'ws'
    const url = `${proto}://${window.location.host}/realtime/tactics/${tacticId}?token=${tokenStore.get()}`
    const ws = new WebSocket(url)
    wsRef.current = ws

    ws.onopen = () => setConnected(true)
    ws.onclose = () => setConnected(false)
    ws.onmessage = (e) => {
      const m = JSON.parse(e.data)
      if (m.type === 'snapshot' || m.type === 'update') setBoard(m.board ?? { pieces: [] })
      else if (m.type === 'presence') setPresence(m.count)
    }

    return () => {
      wsRef.current = null
      ws.close()
    }
  }, [tacticId])

  // Applies a new board locally and broadcasts it. Throttled to ~20/s during a
  // drag; pass force=true (add/remove/drag-end) to send immediately. Without edit
  // rights it never broadcasts — the realtime service would reject the save anyway,
  // so a read-only viewer simply can't push changes (the UI also hides the tools).
  const update = useCallback((next, force = false) => {
    if (!canEdit) return
    setBoard(next)
    const ws = wsRef.current
    if (!ws || ws.readyState !== WebSocket.OPEN) return
    const now = Date.now()
    if (force || now - lastSent.current >= 50) {
      lastSent.current = now
      ws.send(JSON.stringify({ type: 'update', board: next }))
    }
  }, [canEdit])

  return { board, presence, connected, update }
}
