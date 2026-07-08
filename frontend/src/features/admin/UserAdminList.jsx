import { useState } from 'react'
import { t } from '../../lib/i18n'

function UserRow({ user, players, takenSteamIds, currentUserId, onUpdate, onDelete }) {
  const [error, setError] = useState(null)
  const [saved, setSaved] = useState(false)
  const [busy, setBusy] = useState(false)

  const run = async (patch) => {
    setBusy(true)
    setError(null)
    setSaved(false)
    try {
      await onUpdate(user.id, patch)
      setSaved(true)
    } catch (e) {
      setError(e.code ?? 'error.unknown')
    } finally {
      setBusy(false)
    }
  }

  const del = async () => {
    if (!window.confirm(`Delete ${user.name}? This can't be undone. Their uploaded matches are kept but lose their owner.`)) return
    setBusy(true)
    setError(null)
    try {
      await onDelete(user.id)
      // Row unmounts on success — no state to reset.
    } catch (e) {
      setError(e.code ?? 'error.unknown')
      setBusy(false)
    }
  }

  // The linked player may not be in the catalog (e.g. no longer in any visible demo); keep it
  // selectable so it still shows and can be changed.
  const linkedKnown = players.some((p) => p.steam_id === user.steam_id)

  return (
    <tr>
      <td>
        <div className="ua-name">{user.name}</div>
        <div className="ua-email">{user.email}</div>
      </td>
      <td>
        <select value={user.role} onChange={(e) => run({ role: e.target.value })} disabled={busy} aria-label={`Role for ${user.name}`}>
          <option value="member">Member</option>
          <option value="admin">Admin</option>
        </select>
      </td>
      <td>
        <select
          value={user.steam_id ?? ''}
          onChange={(e) => run({ steam_id: e.target.value })}
          disabled={busy}
          aria-label={`Player for ${user.name}`}
        >
          <option value="">— Not linked —</option>
          {user.steam_id && !linkedKnown && (
            <option value={user.steam_id}>{user.steam_id}</option>
          )}
          {players.map((p) => (
            <option
              key={p.steam_id}
              value={p.steam_id}
              disabled={p.steam_id !== user.steam_id && takenSteamIds.has(p.steam_id)}
            >
              {p.name}{takenSteamIds.has(p.steam_id) && p.steam_id !== user.steam_id ? ' (linked)' : ''}
            </option>
          ))}
        </select>
      </td>
      <td>
        {user.teams?.length
          ? user.teams.map((team) => (
              <span key={team.id} className="ua-team-tag" title={team.role}>{team.name}</span>
            ))
          : <span className="muted">—</span>}
      </td>
      <td className="ua-actions">
        {error ? <span className="error">{t(error)}</span> : saved ? <span className="ua-saved">Saved</span> : null}
        {user.id !== currentUserId && (
          <button type="button" className="ua-del" onClick={del} disabled={busy}>Delete</button>
        )}
      </td>
    </tr>
  )
}

export default function UserAdminList({ users, players, currentUserId, onUpdate, onDelete }) {
  const takenSteamIds = new Set(users.map((u) => u.steam_id).filter(Boolean))

  return (
    <div className="ua-wrap">
      <table className="ua-table">
        <thead>
          <tr>
            <th>User</th>
            <th>Role</th>
            <th>Player</th>
            <th>Teams</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          {users.map((user) => (
            <UserRow
              key={user.id}
              user={user}
              players={players}
              takenSteamIds={takenSteamIds}
              currentUserId={currentUserId}
              onUpdate={onUpdate}
              onDelete={onDelete}
            />
          ))}
        </tbody>
      </table>
    </div>
  )
}
