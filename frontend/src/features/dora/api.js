import { useEffect, useState } from 'react'
import { api } from '../../lib/api'

// The four DORA metrics plus the parse Reliability SLO, over a rolling window. Read-only:
// every value is computed by the api from rows the pipeline recorded itself.
export function useDoraMetrics(windowDays) {
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  useEffect(() => {
    let active = true
    setLoading(true)
    setError(null)
    // getPage, not get: this endpoint returns a bare JSON object, not a Laravel Resource,
    // so there is no `data` envelope for the client's default unwrap to strip. Using
    // api.get here silently yields undefined and the page renders nothing at all.
    api.getPage(`/dora/metrics?window=${windowDays}`)
      .then((d) => active && setData(d))
      .catch((e) => active && setError(e.code ?? 'error.unknown'))
      .finally(() => active && setLoading(false))
    return () => {
      active = false
    }
  }, [windowDays])

  return { data, loading, error }
}
