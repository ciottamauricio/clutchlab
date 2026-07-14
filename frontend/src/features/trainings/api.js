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

// Your own invite answer (roster members only).
export const rsvpTraining = (id, going) => api.patch(`/trainings/${id}/rsvp`, { going })

// Homework: the coach assigns/removes; the assignee marks done.
export const createAssignment = (trainingId, data) => api.post(`/trainings/${trainingId}/assignments`, data)
export const toggleAssignmentDone = (trainingId, id, done) =>
  api.patch(`/trainings/${trainingId}/assignments/${id}`, { done })
export const deleteAssignment = (trainingId, id) => api.delete(`/trainings/${trainingId}/assignments/${id}`)
