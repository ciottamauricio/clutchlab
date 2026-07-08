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

export const addPlayer = (teamId, steamId, nickname) =>
  api.post(`/teams/${teamId}/players`, { steam_id: steamId, nickname: nickname || null })
export const removePlayer = (teamId, steamId) => api.delete(`/teams/${teamId}/players/${steamId}`)

// The catalog of every player seen across the caller's matches — the roster pick list.
export function usePlayerCatalog() {
  const [players, setPlayers] = useState([])
  const [error, setError] = useState(null)

  useEffect(() => {
    let active = true
    api.get('/players')
      .then((d) => active && setPlayers(d.players ?? []))
      .catch((e) => active && setError(e.code ?? 'error.unknown'))
    return () => {
      active = false
    }
  }, [])

  return { players, error }
}

// A player's won clutches across the caller's matches (grouped by match+round), loaded on
// demand when the stat board opens the clutch dialog for that player.
export function usePlayerClutches(steamId) {
  const [clutches, setClutches] = useState([])
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(false)

  useEffect(() => {
    if (!steamId) {
      setClutches([])
      return
    }
    let active = true
    setLoading(true)
    setError(null)
    api.get(`/players/${steamId}/clutches`)
      .then((d) => active && setClutches(d.clutches ?? []))
      .catch((e) => active && setError(e.code ?? 'error.unknown'))
      .finally(() => active && setLoading(false))
    return () => {
      active = false
    }
  }, [steamId])

  return { clutches, error, loading }
}

// Roster stat board for a team, aggregated across the team's matches played within an
// optional [from, to] date range. Refetched when the roster changes (bump `reloadKey`) or the
// range changes.
export function useTeamStats(teamId, reloadKey = 0, from = '', to = '') {
  const [players, setPlayers] = useState([])
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(false)

  useEffect(() => {
    if (!teamId) {
      setPlayers([])
      return
    }
    let active = true
    setLoading(true)
    const qs = new URLSearchParams()
    if (from) qs.set('from', from)
    if (to) qs.set('to', to)
    const suffix = qs.toString() ? `?${qs}` : ''
    api.get(`/teams/${teamId}/stats${suffix}`)
      .then((d) => active && setPlayers(d.players ?? []))
      .catch((e) => active && setError(e.code ?? 'error.unknown'))
      .finally(() => active && setLoading(false))
    return () => {
      active = false
    }
  }, [teamId, reloadKey, from, to])

  return { players, error, loading }
}
