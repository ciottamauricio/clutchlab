import { useCallback, useEffect, useState } from 'react'
import { api } from '../../lib/api'

const listMatches = () => api.get('/matches')
const getMatch = (id) => api.get(`/matches/${id}`)
const uploadDemo = (file) => {
  const form = new FormData()
  form.append('demo', file)
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
  }, [id, pollMs])

  return { match, error }
}

export function useUploadDemo(onUploaded) {
  const [uploading, setUploading] = useState(false)
  const [error, setError] = useState(null)

  const upload = useCallback(async (file) => {
    setUploading(true)
    setError(null)
    try {
      const match = await uploadDemo(file)
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
