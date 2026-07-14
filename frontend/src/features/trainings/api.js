import { useCallback, useEffect, useState } from 'react'
import { api } from '../../lib/api'

// Sessions of all the caller's teams, chronological (the UI splits upcoming/past).
export function useTrainings() {
  const [trainings, setTrainings] = useState([])
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(true)

  const refresh = useCallback(async () => {
    try {
      setTrainings(await api.get('/trainings'))
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

  return { trainings, error, loading, refresh }
}

export const createTraining = (data) => api.post('/trainings', data)
export const updateTraining = (id, data) => api.patch(`/trainings/${id}`, data)
export const deleteTraining = (id) => api.delete(`/trainings/${id}`)
