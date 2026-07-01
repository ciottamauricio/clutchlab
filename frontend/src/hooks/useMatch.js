import { useEffect, useState } from 'react'
import { getMatch } from '../lib/api'

const TERMINAL = new Set(['parsed', 'failed'])

// Polls a single match until it reaches a terminal state, then stops. This is
// the client side of the async boundary: no push, so we ask until there's an answer.
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
