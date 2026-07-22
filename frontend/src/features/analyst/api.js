import { useCallback, useState } from 'react'
import { api } from '../../lib/api'

// One question in flight at a time; the previous answer stays visible while the
// next one loads so the panel never blanks.
export function useAnalyst() {
  const [answer, setAnswer] = useState(null)
  const [asked, setAsked] = useState(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState(null)

  const ask = useCallback(async (question) => {
    setLoading(true)
    setError(null)
    try {
      const data = await api.post('/analyst/ask', { question })
      setAnswer(data.answer ?? '')
      setAsked(question)
    } catch (e) {
      setError(e.code ?? 'error.unknown')
    } finally {
      setLoading(false)
    }
  }, [])

  return { answer, asked, loading, error, ask }
}
