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

// The permission grant matrix (catalog + roles per scope + which roles hold each ability) and a
// saver that replaces one role's grants. Admin-only server-side.
export function usePermissionMatrix() {
  const [matrix, setMatrix] = useState(null)
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let active = true
    api.get('/admin/permissions')
      .then((d) => active && setMatrix(d))
      .catch((e) => active && setError(e.code ?? 'error.unknown'))
      .finally(() => active && setLoading(false))
    return () => {
      active = false
    }
  }, [])

  // Replace one (scope, role)'s grants with `keys`, then reflect it locally.
  const setRole = useCallback(async (scope, role, keys) => {
    await api.put('/admin/permissions', { scope, role, keys })
    setMatrix((m) => ({
      ...m,
      grants: { ...m.grants, [scope]: { ...m.grants[scope], [role]: keys } },
    }))
  }, [])

  return { matrix, error, loading, setRole }
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
