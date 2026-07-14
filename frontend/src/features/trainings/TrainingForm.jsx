import { useMemo, useState } from 'react'
import { useTeams, useTeam } from '../teams/api'
import { useTactics } from '../tactics/api'
import { createTraining } from './api'
import { t } from '../../lib/i18n'
import { mapLabel } from '../matches/format'

// Schedule a session: pick a team you may schedule for, a time, the tactics to drill,
// and the expected roster (the selected team's members). Server-checked; codes localized.
export default function TrainingForm({ onCreated }) {
  const { teams } = useTeams()
  const { tactics } = useTactics()
  const manageable = teams.filter((tm) => tm.can?.manage_trainings)

  const [teamId, setTeamId] = useState('')
  const [title, setTitle] = useState('')
  const [when, setWhen] = useState('')
  const [duration, setDuration] = useState('')
  const [notes, setNotes] = useState('')
  const [tacticIds, setTacticIds] = useState([])
  const [playerIds, setPlayerIds] = useState([])
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState(null)
  const [open, setOpen] = useState(false)

  const { team } = useTeam(teamId || null)
  const members = team?.members ?? []

  // Tactics offerable to this team: shared with it, or the caller's own.
  const offerable = useMemo(
    () => tactics.filter((tc) => !tc.team || String(tc.team.id) === String(teamId)),
    [tactics, teamId],
  )

  const toggle = (setter) => (id) =>
    setter((cur) => (cur.includes(id) ? cur.filter((x) => x !== id) : [...cur, id]))

  if (manageable.length === 0) return null

  const submit = async (e) => {
    e.preventDefault()
    setSaving(true)
    setError(null)
    try {
      const training = await createTraining({
        team_id: Number(teamId),
        title,
        notes: notes || null,
        scheduled_at: new Date(when).toISOString(),
        duration_minutes: duration ? Number(duration) : null,
        tactic_ids: tacticIds,
        player_ids: playerIds,
      })
      setTitle(''); setWhen(''); setDuration(''); setNotes(''); setTacticIds([]); setPlayerIds([])
      setOpen(false)
      onCreated?.(training)
    } catch (err) {
      setError(err.code ?? 'error.unknown')
    } finally {
      setSaving(false)
    }
  }

  if (!open) {
    return (
      <button type="button" className="tr-open-form" onClick={() => setOpen(true)}>
        + Schedule training
      </button>
    )
  }

  return (
    <form className="pf-card tr-form" onSubmit={submit}>
      <h3>Schedule training</h3>

      <div className="tr-form-row">
        <label>
          Team
          <select value={teamId} onChange={(e) => { setTeamId(e.target.value); setPlayerIds([]); setTacticIds([]) }} required>
            <option value="" disabled>Pick a team</option>
            {manageable.map((tm) => <option key={tm.id} value={tm.id}>{tm.name}</option>)}
          </select>
        </label>
        <label>
          Title
          <input value={title} onChange={(e) => setTitle(e.target.value)} maxLength={120} placeholder="A-executes + retakes" required />
        </label>
      </div>

      <div className="tr-form-row">
        <label>
          When
          <input type="datetime-local" value={when} onChange={(e) => setWhen(e.target.value)} required />
        </label>
        <label>
          Length (min)
          <input type="number" min="1" max="600" value={duration} onChange={(e) => setDuration(e.target.value)} placeholder="90" />
        </label>
      </div>

      {teamId && (
        <>
          <span className="tr-picker-label">Who&apos;s expected</span>
          <div className="tr-chips">
            {members.map((m) => (
              <button
                key={m.id}
                type="button"
                className={`tr-chip${playerIds.includes(m.id) ? ' on' : ''}`}
                onClick={() => toggle(setPlayerIds)(m.id)}
              >
                {m.name}
              </button>
            ))}
          </div>

          <span className="tr-picker-label">Tactics to drill</span>
          <div className="tr-chips">
            {offerable.length === 0 && <span className="muted">No tactics yet — create them on the Tactics page.</span>}
            {offerable.map((tc) => (
              <button
                key={tc.id}
                type="button"
                className={`tr-chip${tacticIds.includes(tc.id) ? ' on' : ''}`}
                onClick={() => toggle(setTacticIds)(tc.id)}
              >
                {tc.name}{tc.map ? ` · ${mapLabel(tc.map)}` : ''}
              </button>
            ))}
          </div>
        </>
      )}

      <label className="tr-notes">
        Notes
        <textarea value={notes} onChange={(e) => setNotes(e.target.value)} maxLength={2000} rows={2} placeholder="Focus points, VOD to watch first…" />
      </label>

      {error && <p className="error">{t(error)}</p>}

      <div className="tr-form-actions">
        <button type="submit" disabled={saving || !teamId}>Schedule</button>
        <button type="button" className="link-btn" onClick={() => setOpen(false)}>cancel</button>
      </div>
    </form>
  )
}
