import { useState } from 'react'
import { createTactic } from './api'
import { useTeams } from '../teams/api'
import { TACTIC_MAPS } from './maps'
import { mapLabel } from '../matches/format'
import { t } from '../../lib/i18n'

// A new tactic can be born shared. Teams the caller may create into come from
// can.create_tactics; the picker defaults to the first, so a strat drawn during practice
// reaches the team by default — "Private" stays available for a personal draft.
export default function CreateTactic({ onCreated }) {
  const { teams } = useTeams()
  const creatable = teams.filter((tm) => tm.can?.create_tactics)

  const [name, setName] = useState('')
  const [map, setMap] = useState('')
  // null = untouched; the effective value defaults to the first creatable team at render,
  // so it's right once teams load without an effect racing the user's choice.
  const [teamId, setTeamId] = useState(null)
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)

  const effectiveTeamId = teamId ?? (creatable[0] ? String(creatable[0].id) : '')

  const submit = async (e) => {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      const tactic = await createTactic(name, map || null, effectiveTeamId ? Number(effectiveTeamId) : null)
      setName('')
      setMap('')
      onCreated?.(tactic)
    } catch (err) {
      setError(err.code ?? 'error.unknown')
    } finally {
      setBusy(false)
    }
  }

  return (
    <form className="inline-form" onSubmit={submit}>
      <input placeholder="New tactic name" value={name} onChange={(e) => setName(e.target.value)} />
      <select value={map} onChange={(e) => setMap(e.target.value)} aria-label="Map">
        <option value="">No map (plain field)</option>
        {TACTIC_MAPS.map((m) => (
          <option key={m} value={m}>{mapLabel(m)}</option>
        ))}
      </select>
      {creatable.length > 0 && (
        <select value={effectiveTeamId} onChange={(e) => setTeamId(e.target.value)} aria-label="Share with">
          {creatable.map((tm) => (
            <option key={tm.id} value={tm.id}>{tm.name}</option>
          ))}
          <option value="">Private (only me)</option>
        </select>
      )}
      <button type="submit" disabled={!name || busy}>Create tactic</button>
      {error && <p className="error">{t(error)}</p>}
    </form>
  )
}
