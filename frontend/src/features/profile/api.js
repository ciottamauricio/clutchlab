import { useEffect, useState } from 'react'
import { api } from '../../lib/api'

// In-game roles offered in the profile editor (value stored, label shown).
export const PLAYER_ROLES = [
  { value: 'awper', label: 'AWPer' },
  { value: 'rifler', label: 'Rifler' },
  { value: 'igl', label: 'In-game leader' },
  { value: 'entry', label: 'Entry fragger' },
  { value: 'lurker', label: 'Lurker' },
  { value: 'support', label: 'Support' },
  { value: 'coach', label: 'Coach' },
]

// The gear fields, in display order.
export const GEAR_FIELDS = [
  { key: 'pc', label: 'PC / rig' },
  { key: 'mouse', label: 'Mouse' },
  { key: 'keyboard', label: 'Keyboard' },
  { key: 'headset', label: 'Headset' },
  { key: 'monitor', label: 'Monitor' },
  { key: 'mousepad', label: 'Mousepad' },
]

export const updateProfile = (data) => api.patch('/profile', data)

export const changePassword = (data) => api.patch('/profile/password', data)

// The caller's own aggregate stats, or null when no SteamID is linked to their account.
export function useProfileStats() {
  const [stats, setStats] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let active = true
    api.get('/profile/stats')
      .then((d) => active && setStats(d.stats ?? null))
      .catch(() => {})
      .finally(() => active && setLoading(false))
    return () => {
      active = false
    }
  }, [])

  return { stats, loading }
}
