import { useState } from 'react'
import { useTeam, addMember, removeMember } from './api'
import { useAuth } from '../auth/AuthContext'
import { t } from '../../lib/i18n'
import TeamRoster from './TeamRoster'
import TeamStats from './TeamStats'

const ROLES = ['owner', 'igl', 'player', 'coach']

export default function TeamDetail({ teamId }) {
  const { user } = useAuth()
  const { team, error, refresh } = useTeam(teamId)
  const [email, setEmail] = useState('')
  const [role, setRole] = useState('player')
  const [formError, setFormError] = useState(null)
  // Bumped when the roster changes, so the stat board refetches.
  const [rosterKey, setRosterKey] = useState(0)

  const onRosterChanged = () => {
    setRosterKey((k) => k + 1)
    refresh()
  }

  if (!teamId) return <p className="muted">Select a team to see its members.</p>
  if (error) return <p className="error">{t(error)}</p>
  if (!team) return <p className="muted">Loading…</p>

  const canManage = team.members.find((m) => m.id === user.id)?.role === 'owner'

  const add = async (e) => {
    e.preventDefault()
    setFormError(null)
    try {
      await addMember(team.id, email, role)
      setEmail('')
      refresh()
    } catch (err) {
      setFormError(err.code ?? 'error.unknown')
    }
  }

  const remove = async (userId) => {
    await removeMember(team.id, userId)
    refresh()
  }

  return (
    <section className="dashboard">
      <h2>{team.name}</h2>
      <table className="members">
        <thead>
          <tr>
            <th>Member</th>
            <th>Role</th>
            {canManage && <th />}
          </tr>
        </thead>
        <tbody>
          {team.members.map((m) => (
            <tr key={m.id}>
              <td>{m.name} <span className="muted">{m.email}</span></td>
              <td><span className="role">{m.role}</span></td>
              {canManage && (
                <td>
                  {m.id !== user.id && (
                    <button type="button" className="link-btn" onClick={() => remove(m.id)}>remove</button>
                  )}
                </td>
              )}
            </tr>
          ))}
        </tbody>
      </table>

      {canManage && (
        <form className="inline-form" onSubmit={add}>
          <input type="email" placeholder="member email" value={email} onChange={(e) => setEmail(e.target.value)} />
          <select value={role} onChange={(e) => setRole(e.target.value)}>
            {ROLES.map((r) => <option key={r} value={r}>{r}</option>)}
          </select>
          <button type="submit" disabled={!email}>Add member</button>
          {formError && <p className="error">{t(formError)}</p>}
        </form>
      )}

      <TeamRoster teamId={team.id} roster={team.players ?? []} canManage={canManage} onChanged={onRosterChanged} />
      <TeamStats teamId={team.id} reloadKey={rosterKey} />
    </section>
  )
}
