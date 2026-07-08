import { useState } from 'react'
import { t } from '../../lib/i18n'

function UserRow({ user, onUpdate }) {
  const [steam, setSteam] = useState(user.steam_id ?? '')
  const [error, setError] = useState(null)
  const [saved, setSaved] = useState(false)
  const [busy, setBusy] = useState(false)

  const run = async (patch, resetSaved = true) => {
    setBusy(true)
    setError(null)
    if (resetSaved) setSaved(false)
    try {
      await onUpdate(user.id, patch)
      setSaved(true)
    } catch (e) {
      setError(e.code ?? 'error.unknown')
    } finally {
      setBusy(false)
    }
  }

  const changeRole = (e) => run({ role: e.target.value })
  const saveSteam = (e) => {
    e.preventDefault()
    if ((steam || '') === (user.steam_id ?? '')) return
    run({ steam_id: steam })
  }

  return (
    <tr>
      <td>
        <div className="ua-name">{user.name}</div>
        <div className="ua-email">{user.email}</div>
      </td>
      <td>
        <select value={user.role} onChange={changeRole} disabled={busy} aria-label={`Role for ${user.name}`}>
          <option value="member">Member</option>
          <option value="admin">Admin</option>
        </select>
      </td>
      <td>
        <form className="ua-steam" onSubmit={saveSteam}>
          <input
            value={steam}
            onChange={(e) => setSteam(e.target.value)}
            placeholder="SteamID64"
            inputMode="numeric"
            aria-label={`SteamID for ${user.name}`}
          />
          <button type="submit" className="link-btn" disabled={busy || (steam || '') === (user.steam_id ?? '')}>
            Save
          </button>
        </form>
      </td>
      <td className="ua-teams">
        {user.teams?.length
          ? user.teams.map((team) => (
              <span key={team.id} className="ua-team-tag" title={team.role}>{team.name}</span>
            ))
          : <span className="muted">—</span>}
      </td>
      <td className="ua-status">
        {error ? <span className="error">{t(error)}</span> : saved ? <span className="ua-saved">Saved</span> : null}
      </td>
    </tr>
  )
}

export default function UserAdminList({ users, onUpdate }) {
  return (
    <div className="ua-wrap">
      <table className="ua-table">
        <thead>
          <tr>
            <th>User</th>
            <th>Role</th>
            <th>Linked SteamID</th>
            <th>Teams</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          {users.map((user) => (
            <UserRow key={user.id} user={user} onUpdate={onUpdate} />
          ))}
        </tbody>
      </table>
    </div>
  )
}
