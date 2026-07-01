import { useCallback, useEffect, useState } from 'react'
import { listMatches } from '../lib/api'

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
