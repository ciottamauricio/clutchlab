import { useCallback, useState } from 'react'
import { api } from '../../lib/api'

// Build a query string, dropping empty/false params (so an unchecked box doesn't
// force a filter). Booleans become 1.
function qs(params) {
  const p = new URLSearchParams()
  for (const [k, v] of Object.entries(params)) {
    if (v === '' || v === null || v === undefined || v === false) continue
    p.append(k, v === true ? '1' : v)
  }
  const s = p.toString()
  return s ? `?${s}` : ''
}

export function useSearch() {
  const [hits, setHits] = useState([])
  const [total, setTotal] = useState(0)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState(null)
  const [ran, setRan] = useState(false)

  // kind is 'kills' or 'rounds'.
  const run = useCallback(async (kind, params) => {
    setLoading(true)
    setError(null)
    try {
      const data = await api.get(`/search/${kind}${qs(params)}`)
      setHits(data.hits ?? [])
      setTotal(data.total ?? 0)
    } catch (e) {
      setError(e.code ?? 'error.unknown')
      setHits([])
      setTotal(0)
    } finally {
      setLoading(false)
      setRan(true)
    }
  }, [])

  return { hits, total, loading, error, ran, run }
}
