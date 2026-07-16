import { useMemo, useState } from 'react'
import { useTeams, useTeam } from '../teams/api'
import { useTactics } from '../tactics/api'
import { createTraining, updateTraining } from './api'
import HomeworkPicker from './HomeworkPicker'
import { t } from '../../lib/i18n'
import { mapLabel } from '../matches/format'

// A datetime-local input wants "YYYY-MM-DDTHH:mm" in local time; toISOString gives UTC.
// Slice the ISO string after shifting by the zone offset so the field shows local wall time.
const toLocalInput = (iso) => {
  if (!iso) return ''
  const d = new Date(iso)
  return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16)
}

// Schedule or edit a session: pick a team you may schedule for, a time, the tactics to
// drill, and the expected roster (the selected team's members). In edit mode the team is
// fixed (a session never changes team). Server-checked; codes localized.
export default function TrainingForm({ training, onSaved, onClose }) {
  const editing = Boolean(training)
  const { teams } = useTeams()
  const { tactics } = useTactics()
  const manageable = teams.filter((tm) => tm.can?.manage_trainings)

  const [teamId, setTeamId] = useState(editing ? String(training.team?.id ?? '') : '')
  const [title, setTitle] = useState(training?.title ?? '')
  const [when, setWhen] = useState(toLocalInput(training?.scheduled_at))
  const [duration, setDuration] = useState(training?.duration_minutes ? String(training.duration_minutes) : '')
  const [notes, setNotes] = useState(training?.notes ?? '')
  const [tacticIds, setTacticIds] = useState((training?.tactics ?? []).map((tc) => tc.id))
  const [playerIds, setPlayerIds] = useState((training?.players ?? []).map((p) => p.id))
  const [assignments, setAssignments] = useState([]) // create-only: [{ user_id, map, nade_type }]
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState(null)
  const [open, setOpen] = useState(editing)

  const { team } = useTeam(teamId || null)
  const members = team?.members ?? []

  // Tactics offerable to this team: shared with it, or the caller's own.
  const offerable = useMemo(
    () => tactics.filter((tc) => !tc.team || String(tc.team.id) === String(teamId)),
    [tactics, teamId],
  )

  const toggle = (setter) => (id) =>
    setter((cur) => (cur.includes(id) ? cur.filter((x) => x !== id) : [...cur, id]))

  // Dropping a player from the roster drops their homework too — you can't assign to
  // someone who won't be there (the server enforces this; we keep the form honest).
  const togglePlayer = (id) => {
    setPlayerIds((cur) => {
      const removing = cur.includes(id)
      if (removing) setAssignments((as) => as.filter((a) => a.user_id !== id))
      return removing ? cur.filter((x) => x !== id) : [...cur, id]
    })
  }

  // A practice with nobody to run it or nothing to drill isn't a session — the server
  // enforces this too, but we'd rather explain it here than bounce a 422.
  const incomplete = playerIds.length === 0 || tacticIds.length === 0

  if (!editing && manageable.length === 0) return null

  const close = () => {
    setOpen(false)
    onClose?.()
  }

  const submit = async (e) => {
    e.preventDefault()
    setSaving(true)
    setError(null)
    const payload = {
      title,
      notes: notes || null,
      scheduled_at: new Date(when).toISOString(),
      duration_minutes: duration ? Number(duration) : null,
      tactic_ids: tacticIds,
      player_ids: playerIds,
    }
    try {
      const saved = editing
        ? await updateTraining(training.id, payload)
        : await createTraining({ team_id: Number(teamId), ...payload, assignments })
      if (!editing) {
        setTitle(''); setWhen(''); setDuration(''); setNotes(''); setTacticIds([]); setPlayerIds([]); setAssignments([])
      }
      setOpen(editing)
      onSaved?.(saved)
      onClose?.()
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
      <h3>{editing ? 'Edit training' : 'Schedule training'}</h3>

      <div className="tr-form-row">
        <label>
          Team
          <select
            value={teamId}
            onChange={(e) => { setTeamId(e.target.value); setPlayerIds([]); setTacticIds([]) }}
            disabled={editing}
            required
          >
            <option value="" disabled>Pick a team</option>
            {manageable.map((tm) => <option key={tm.id} value={tm.id}>{tm.name}</option>)}
            {editing && !manageable.some((tm) => String(tm.id) === teamId) && (
              <option value={teamId}>{training.team?.name}</option>
            )}
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
                onClick={() => togglePlayer(m.id)}
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

          {!editing && playerIds.length > 0 && (
            <HomeworkPicker
              roster={members.filter((m) => playerIds.includes(m.id))}
              tactics={offerable.filter((tc) => tacticIds.includes(tc.id))}
              assignments={assignments}
              onChange={setAssignments}
            />
          )}
        </>
      )}

      <label className="tr-notes">
        Notes
        <textarea value={notes} onChange={(e) => setNotes(e.target.value)} maxLength={2000} rows={2} placeholder="Focus points, VOD to watch first…" />
      </label>

      {error && <p className="error">{t(error)}</p>}

      <div className="tr-form-actions">
        <button type="submit" disabled={saving || !teamId || incomplete}>{editing ? 'Save changes' : 'Schedule'}</button>
        <button type="button" className="link-btn" onClick={close}>cancel</button>
        {teamId && incomplete && (
          <span className="tr-form-hint">Pick at least one player and one tactic.</span>
        )}
      </div>
    </form>
  )
}
