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

  return { users, error, loading, update }
}
