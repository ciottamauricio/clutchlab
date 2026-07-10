import { useState } from 'react'
import { useTeams } from '../teams/api'
import { useUpdateMatchTeam } from './api'
import { t } from '../../lib/i18n'

// Shows who a match is shared with, and — for someone who can manage it — a control to move it
// between private and any team they may upload to. "Private" means only the uploader sees it.
export default function MatchVisibility({ match, onChanged }) {
  const { teams } = useTeams()
  const { setTeam, saving, error } = useUpdateMatchTeam(onChanged)
  const [editing, setEditing] = useState(false)

  const canManage = match.can?.delete
  const uploadable = (teams ?? []).filter((tm) => tm.can?.upload_match)
  const current = match.team?.name

  const change = async (e) => {
    const value = e.target.value
    try {
      await setTeam(match.id, value === '' ? null : Number(value))
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
        defaultValue={match.team_id ?? ''}
        onChange={change}
        disabled={saving}
        autoFocus
      >
        <option value="">Private (only me)</option>
        {uploadable.map((tm) => (
          <option key={tm.id} value={tm.id}>{tm.name}</option>
        ))}
      </select>
      <button type="button" className="link-btn" onClick={() => setEditing(false)} disabled={saving}>Cancel</button>
      {error && <span className="error mv-error">{t(error)}</span>}
    </div>
  )
}
