import { useState } from 'react'
import { useAuth } from '../features/auth/AuthContext'
import { useProfileStats, PLAYER_ROLES, GEAR_FIELDS } from '../features/profile/api'
import ProfileForm from '../features/profile/ProfileForm'
import ProfileStats from '../features/profile/ProfileStats'

const roleLabel = (value) => PLAYER_ROLES.find((r) => r.value === value)?.label

export default function ProfilePage() {
  const { user, refreshUser } = useAuth()
  const { stats } = useProfileStats()
  const [editing, setEditing] = useState(false)

  if (!user) return null

  const gear = GEAR_FIELDS.map(({ key, label }) => ({ label, value: user.gear?.[key] })).filter((g) => g.value)

  const onSaved = async () => {
    await refreshUser()
    setEditing(false)
  }

  return (
    <section className="profile">
      <header className="pf-head">
        <div>
          <h2>{user.name}</h2>
          <div className="pf-sub">
            {roleLabel(user.player_role) && <span className="pf-role">{roleLabel(user.player_role)}</span>}
            <span className="muted">{user.email}</span>
            {user.is_admin && <span className="pf-badge">Admin</span>}
          </div>
        </div>
        {!editing && <button type="button" onClick={() => setEditing(true)}>Edit profile</button>}
      </header>

      {editing ? (
        <ProfileForm user={user} onSaved={onSaved} onCancel={() => setEditing(false)} />
      ) : (
        <div className="pf-grid">
          <div className="pf-card">
            <h3>About</h3>
            {user.bio ? <p className="pf-bio">{user.bio}</p> : <p className="muted">No bio yet.</p>}

            <h3 className="pf-gear-title">Setup</h3>
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

          <ProfileStats stats={stats} />
        </div>
      )}
    </section>
  )
}
