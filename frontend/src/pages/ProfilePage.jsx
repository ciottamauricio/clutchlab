import { useState } from 'react'
import { useAuth } from '../features/auth/AuthContext'
import { useProfileStats, PLAYER_ROLES, GEAR_FIELDS } from '../features/profile/api'
import ProfileForm from '../features/profile/ProfileForm'
import ProfileStats from '../features/profile/ProfileStats'
import RecentForm from '../features/profile/RecentForm'
import RecentClutches from '../features/profile/RecentClutches'
import WeaponBreakdown from '../features/profile/WeaponBreakdown'

const roleLabel = (value) => PLAYER_ROLES.find((r) => r.value === value)?.label

// The landing page after login: a player-card readout of who you are (role, bio, linked
// stats) plus your loadout and account details. "Edit profile" swaps the card for the edit
// form in place — where changing your password also lives.
export default function ProfilePage() {
  const { user, refreshUser } = useAuth()
  const { stats, recent, weapons, clutches } = useProfileStats()
  const [editing, setEditing] = useState(false)

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
        <>
          <RecentForm recent={recent} />

          {stats && <RecentClutches clutches={clutches} playerName={user.name} />}

          <div className="pf-grid">
            <WeaponBreakdown weapons={weapons} />

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
          </div>

          <div className="pf-card pf-account">
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
        </>
      )}
    </section>
  )
}
