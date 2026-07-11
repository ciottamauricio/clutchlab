import { useState } from 'react'
import { useTeams } from '../teams/api'
import { useUpdateTacticTeam } from './api'
import { t } from '../../lib/i18n'

// Shows who a tactic is shared with and — for its creator — a control to move it between
// private and any team they belong to. Members of that team can then open and co-edit it.
// Reuses the .mv styles from the match visibility control (same readout, same layout).
export default function TacticShare({ tactic, onChanged }) {
  const { teams } = useTeams()
  const { setTeam, saving, error } = useUpdateTacticTeam(onChanged)
  const [editing, setEditing] = useState(false)

  const canManage = tactic.can?.delete
  const current = tactic.team?.name

  const change = async (e) => {
    const value = e.target.value
    try {
      await setTeam(tactic.id, value === '' ? null : Number(value))
      setEditing(false)
    } catch {
      // error surfaced below; keep the control open to retry
    }
  }

  if (!editing) {
    return (
      <div className="mv">
        <span className="mv-label">Shared with</span>
        <span className={`mv-value${current ? '' : ' mv-private'}`}>{current ?? 'Private'}</span>
        {canManage && (
          <button type="button" className="link-btn mv-edit" onClick={() => setEditing(true)}>Change</button>
        )}
      </div>
    )
  }

  return (
    <div className="mv">
      <span className="mv-label">Shared with</span>
      <select
        className="mv-select"
        defaultValue={tactic.team_id ?? ''}
        onChange={change}
        disabled={saving}
        autoFocus
      >
        <option value="">Private (only me)</option>
        {(teams ?? []).map((tm) => (
          <option key={tm.id} value={tm.id}>{tm.name}</option>
        ))}
      </select>
      <button type="button" className="link-btn" onClick={() => setEditing(false)} disabled={saving}>Cancel</button>
      {error && <span className="error mv-error">{t(error)}</span>}
    </div>
  )
}
