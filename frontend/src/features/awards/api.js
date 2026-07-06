import { useEffect, useState } from 'react'
import { api } from '../../lib/api'

// Superlative leaderboards across the caller's matches, optionally scoped to a roster team
// and/or a map (see api/docs/domains/awards.md). Refetches when a filter changes.
export function useAwards(teamId, map) {
  const [awards, setAwards] = useState([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState(null)

  useEffect(() => {
    let active = true
    setLoading(true)
    setError(null)
    const p = new URLSearchParams()
    if (teamId) p.append('team_id', teamId)
    if (map) p.append('map', map)
    const qs = p.toString() ? `?${p}` : ''
    api.get(`/awards${qs}`)
      .then((d) => active && setAwards(d.awards ?? []))
      .catch((e) => active && setError(e.code ?? 'error.unknown'))
      .finally(() => active && setLoading(false))
    return () => {
      active = false
    }
  }, [teamId, map])

  return { awards, loading, error }
}

// The kills behind one award for one player (drill-down from a leaderboard row), in the same
// map scope. Loaded on demand when the kill dialog opens.
export function useAwardKills(key, steamId, map) {
  const [kills, setKills] = useState([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState(null)

  useEffect(() => {
    if (!key || !steamId) {
      setKills([])
      return
    }
    let active = true
    setLoading(true)
    setError(null)
    const p = new URLSearchParams({ key, steam_id: steamId })
    if (map) p.append('map', map)
    api.get(`/awards/kills?${p}`)
      .then((d) => active && setKills(d.kills ?? []))
      .catch((e) => active && setError(e.code ?? 'error.unknown'))
      .finally(() => active && setLoading(false))
    return () => {
      active = false
    }
  }, [key, steamId, map])

  return { kills, loading, error }
}
