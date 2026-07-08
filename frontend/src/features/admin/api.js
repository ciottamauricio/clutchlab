import { useCallback, useEffect, useState } from 'react'
import { api } from '../../lib/api'

// Every user on the platform, for the master-admin panel. Admin-only server-side (403 for
// anyone else), so the hook simply surfaces that error.
export function useUsers() {
  const [users, setUsers] = useState([])
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(true)

  const refresh = useCallback(async () => {
    try {
      setUsers(await api.get('/admin/users'))
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

  // Patch one user (role and/or steam_id) and swap the updated row into place.
  const update = useCallback(async (id, patch) => {
    const updated = await api.patch(`/admin/users/${id}`, patch)
    setUsers((list) => list.map((u) => (u.id === id ? updated : u)))
    return updated
  }, [])

  const remove = useCallback(async (id) => {
    await api.delete(`/admin/users/${id}`)
    setUsers((list) => list.filter((u) => u.id !== id))
  }, [])

  return { users, error, loading, update, remove }
}

// Every player (SteamID64 + name) seen across all matches — the pick list for linking an
// account to its demo identity. Admin-only server-side.
export function useKnownPlayers() {
  const [players, setPlayers] = useState([])

  useEffect(() => {
    let active = true
    api.get('/admin/players')
      .then((d) => active && setPlayers(d.players ?? []))
      .catch(() => {})
    return () => {
      active = false
    }
  }, [])

  return players
}
