import { useCallback, useEffect, useState } from 'react'
import { api } from '../../lib/api'

export function useTeams() {
  const [teams, setTeams] = useState([])
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(true)

  const refresh = useCallback(async () => {
    try {
      setTeams(await api.get('/teams'))
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

  return { teams, error, loading, refresh }
}

export function useTeam(id) {
  const [team, setTeam] = useState(null)
  const [error, setError] = useState(null)

  const refresh = useCallback(async () => {
    if (!id) {
      setTeam(null)
      return
    }
    try {
      setTeam(await api.get(`/teams/${id}`))
      setError(null)
    } catch (e) {
      setError(e.code ?? 'error.unknown')
    }
  }, [id])

  useEffect(() => {
    refresh()
  }, [refresh])

  return { team, error, refresh }
}

export const createTeam = (name) => api.post('/teams', { name })
export const addMember = (teamId, email, role) => api.post(`/teams/${teamId}/members`, { email, role })
export const removeMember = (teamId, userId) => api.delete(`/teams/${teamId}/members/${userId}`)
