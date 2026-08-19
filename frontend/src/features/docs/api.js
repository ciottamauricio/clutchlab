import { useCallback, useState } from 'react'
import { api } from '../../lib/api'

// One question in flight at a time. Sources arrive with the answer rather than being
// fetched separately: they are the same retrieval the answer was generated from, and
// splitting them into two calls would let the page show citations from one run next to
// prose from another.
export function useDocsQuestion() {
  const [answer, setAnswer] = useState(null)
  const [sources, setSources] = useState([])
  const [asked, setAsked] = useState(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState(null)

  const ask = useCallback(async (question) => {
    setLoading(true)
    setError(null)
    try {
      const data = await api.post('/docs/ask', { question })
      setAnswer(data.answer ?? '')
      setSources(data.sources ?? [])
      setAsked(question)
    } catch (e) {
      setError(e.code ?? 'error.unknown')
    } finally {
      setLoading(false)
    }
  }, [])

  return { answer, sources, asked, loading, error, ask }
}
