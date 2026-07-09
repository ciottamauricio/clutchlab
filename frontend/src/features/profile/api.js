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

// The caller's own profile analytics: aggregate stats (null when no SteamID is linked),
// recent-form series, top weapons, and recent clutches.
export function useProfileStats() {
  const [data, setData] = useState({ stats: null, recent: [], weapons: [], clutches: [] })
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let active = true
    api.get('/profile/stats')
      .then((d) => active && setData({
        stats: d.stats ?? null,
        recent: d.recent ?? [],
        weapons: d.weapons ?? [],
        clutches: d.clutches ?? [],
      }))
      .catch(() => {})
      .finally(() => active && setLoading(false))
    return () => {
      active = false
    }
  }, [])

  return { ...data, loading }
}
