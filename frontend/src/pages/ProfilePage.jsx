import { useEffect, useState } from 'react'
import { useLocation } from 'react-router-dom'
import { useAuth } from '../features/auth/AuthContext'
import { useProfileStats, PLAYER_ROLES, GEAR_FIELDS } from '../features/profile/api'
import ProfileForm from '../features/profile/ProfileForm'
import ProfileStats from '../features/profile/ProfileStats'

const roleLabel = (value) => PLAYER_ROLES.find((r) => r.value === value)?.label

// The landing page after login: a player-card readout of who you are (role, bio, linked
// stats) plus your loadout and account details. "Edit profile" — here or from the account
// menu — swaps the card for the edit form in place.
export default function ProfilePage() {
  const { user, refreshUser } = useAuth()
  const { stats } = useProfileStats()
  const location = useLocation()
  const [editing, setEditing] = useState(Boolean(location.state?.edit))

  // The account-menu shortcut navigates here with { state: { edit: true } } — react to it
  // even if we're already on this page (location.key changes on every navigate() call).
  useEffect(() => {
    if (location.state?.edit) setEditing(true)
  }, [location.key]) // eslint-disable-line react-hooks/exhaustive-deps

  if (!user) return null

  const gear = GEAR_FIELDS.map(({ key, label }) => ({ label, value: user.gear?.[key] })).filter((g) => g.value)

  const onSaved = async () => {
    await refreshUser()
    setEditing(false)
  }

  return (
    <section className="profile">
      <div className="player-card">
        <span className="pc-corner pc-corner-tl" aria-hidden="true" />
        <span className="pc-corner pc-corner-tr" aria-hidden="true" />
        <span className="pc-corner pc-corner-bl" aria-hidden="true" />
        <span className="pc-corner pc-corner-br" aria-hidden="true" />

        <div className="pc-id">
          <div>
            <h1 className="pc-name">{user.name}</h1>
            <div className="pc-meta">
              {roleLabel(user.player_role) && <span className="pc-role">{roleLabel(user.player_role)}</span>}
              <span className="muted">{user.email}</span>
              {user.is_admin && <span className="pc-badge">Admin</span>}
            </div>
            {user.bio && <p className="pc-bio">&ldquo;{user.bio}&rdquo;</p>}
          </div>
          {!editing && <button type="button" className="pc-edit" onClick={() => setEditing(true)}>Edit profile</button>}
        </div>

        <ProfileStats stats={stats} />
      </div>

      {editing ? (
        <ProfileForm user={user} onSaved={onSaved} onCancel={() => setEditing(false)} />
      ) : (
        <div className="pf-grid">
          <div className="pf-card">
            <h3>Loadout</h3>
            {gear.length > 0 ? (
              <dl className="pf-gear">
                {gear.map((g) => (
                  <div className="pf-gear-row" key={g.label}>
                    <dt>{g.label}</dt>
                    <dd>{g.value}</dd>
                  </div>
                ))}
              </dl>
            ) : (
              <p className="muted">No setup added yet.</p>
            )}
          </div>

          <div className="pf-card">
            <h3>Account</h3>
            <dl className="pf-gear">
              <div className="pf-gear-row">
                <dt>Global role</dt>
                <dd>{user.is_admin ? 'Admin' : 'Member'}</dd>
              </div>
              <div className="pf-gear-row">
                <dt>Linked SteamID</dt>
                <dd className="mono-dim">{user.steam_id ?? 'Not linked'}</dd>
              </div>
            </dl>
          </div>
        </div>
      )}
    </section>
  )
}
