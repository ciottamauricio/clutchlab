import { useCallback, useEffect, useState } from 'react'
import { api, ApiError, tokenStore } from '../../lib/api'

const listMatches = () => api.get('/matches')
const getMatch = (id) => api.get(`/matches/${id}`)
const getKillPositions = (id) => api.get(`/matches/${id}/kill-positions`)
const getClutches = (id) => api.get(`/matches/${id}/clutches`)
const getTeamStats = (id) => api.get(`/matches/${id}/team-stats`)
const deleteMatch = (id) => api.delete(`/matches/${id}`)
const reparseMatch = (id) => api.post(`/matches/${id}/reparse`)
const uploadDemo = (file, teamId) => {
  const form = new FormData()
  form.append('demo', file)
  if (teamId) form.append('team_id', teamId)
  return api.postForm('/matches', form)
}

// Data hooks for the matches feature. Plain useState/useEffect for now; TanStack
// Query is the intended direction once caching/invalidation is worth the dependency
// (see frontend/ENGINEERING.md).

const TERMINAL = new Set(['parsed', 'failed'])

// Polls the match list so status changes (queued -> parsing -> parsed) show up
// without a manual refresh.
export function useMatches(pollMs = 3000) {
  const [matches, setMatches] = useState([])
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(true)

  const refresh = useCallback(async () => {
    try {
      setMatches(await listMatches())
      setError(null)
    } catch (e) {
      setError(e.code ?? 'error.unknown')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    refresh()
    const id = setInterval(refresh, pollMs)
    return () => clearInterval(id)
  }, [refresh, pollMs])

  return { matches, error, loading, refresh }
}

// Polls a single match until it reaches a terminal state, then stops. This is the
// client side of the async boundary: no push, so we ask until there's an answer.
export function useMatch(id, pollMs = 2000) {
  const [match, setMatch] = useState(null)
  const [error, setError] = useState(null)
  // Bumping this restarts the effect — used after a reparse flips a terminal match back
  // to `queued`, so polling resumes until it reaches `parsed` again.
  const [reloadKey, setReloadKey] = useState(0)
  const reload = useCallback(() => setReloadKey((k) => k + 1), [])

  useEffect(() => {
    if (!id) {
      setMatch(null)
      return
    }

    let active = true
    let timer

    const tick = async () => {
      try {
        const data = await getMatch(id)
        if (!active) return
        setMatch(data)
        setError(null)
        if (!TERMINAL.has(data.status)) {
          timer = setTimeout(tick, pollMs)
        }
      } catch (e) {
        if (active) setError(e.code ?? 'error.unknown')
      }
    }

    tick()
    return () => {
      active = false
      clearTimeout(timer)
    }
  }, [id, pollMs, reloadKey])

  return { match, error, reload }
}

export function useReparseMatch(onReparsed) {
  const [reparsing, setReparsing] = useState(false)
  const [error, setError] = useState(null)

  const reparse = useCallback(async (id) => {
    setReparsing(true)
    setError(null)
    try {
      await reparseMatch(id)
      onReparsed?.(id)
    } catch (e) {
      setError(e.code ?? 'error.unknown')
    } finally {
      setReparsing(false)
    }
  }, [onReparsed])

  return { reparse, reparsing, error }
}

// Loads every kill position for a match once (see api/docs/domains/heatmap.md); the
// heatmap filters this set client-side, so no refetch on filter changes.
export function useKillPositions(id) {
  const [data, setData] = useState({ map: null, tickRate: null, demo: null, points: [] })
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState(null)

  useEffect(() => {
    if (!id) {
      setData({ map: null, tickRate: null, demo: null, points: [] })
      return
    }
    let active = true
    setLoading(true)
    getKillPositions(id)
      .then((d) => active && setData({ map: d.map, tickRate: d.tick_rate, demo: d.demo, points: d.points ?? [] }))
      .catch((e) => active && setError(e.code ?? 'error.unknown'))
      .finally(() => active && setLoading(false))
    return () => {
      active = false
    }
  }, [id])

  return { ...data, loading, error }
}

// Clutches for a match (see MatchController@clutches): grouped by the round/clutcher.
export function useClutches(id) {
  const [data, setData] = useState({ clutches: [], tickRate: null, demo: null })
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState(null)

  useEffect(() => {
    if (!id) {
      setData({ clutches: [], tickRate: null, demo: null })
      return
    }
    let active = true
    setLoading(true)
    getClutches(id)
      .then((d) => active && setData({ clutches: d.clutches ?? [], tickRate: d.tick_rate, demo: d.demo }))
      .catch((e) => active && setError(e.code ?? 'error.unknown'))
      .finally(() => active && setLoading(false))
    return () => {
      active = false
    }
  }, [id])

  return { ...data, loading, error }
}

// Side-by-side CT vs T comparison for the match (see MatchController@teamStats).
export function useTeamComparison(id) {
  const [data, setData] = useState({ ct: null, t: null })
  const [error, setError] = useState(null)

  useEffect(() => {
    if (!id) {
      setData({ ct: null, t: null })
      return
    }
    let active = true
    getTeamStats(id)
      .then((d) => active && setData({ ct: d.ct, t: d.t }))
      .catch((e) => active && setError(e.code ?? 'error.unknown'))
    return () => {
      active = false
    }
  }, [id])

  return { ...data, error }
}

// Streams the stored .dem through the api (auth header required, so not a plain link)
// and triggers a browser download named after the original file.
export async function downloadDemo(id, filename) {
  const res = await fetch(`/api/matches/${id}/demo`, {
    headers: { Accept: 'application/octet-stream', Authorization: `Bearer ${tokenStore.get()}` },
  })
  if (!res.ok) throw new ApiError('error.unknown', res.status)
  const blob = await res.blob()
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename || 'demo.dem'
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(url)
}

export function useDeleteMatch(onDeleted) {
  const [deletingId, setDeletingId] = useState(null)
  const [error, setError] = useState(null)

  const remove = useCallback(async (id) => {
    setDeletingId(id)
    setError(null)
    try {
      await deleteMatch(id)
      onDeleted?.(id)
    } catch (e) {
      setError(e.code ?? 'error.unknown')
    } finally {
      setDeletingId(null)
    }
  }, [onDeleted])

  return { remove, deletingId, error }
}

export function useUploadDemo(onUploaded) {
  const [uploading, setUploading] = useState(false)
  const [error, setError] = useState(null)

  const upload = useCallback(async (file, teamId) => {
    setUploading(true)
    setError(null)
    try {
      const match = await uploadDemo(file, teamId)
      onUploaded?.(match)
      return match
    } catch (e) {
      setError(e.code ?? 'error.unknown')
    } finally {
      setUploading(false)
    }
  }, [onUploaded])

  return { upload, uploading, error }
}
