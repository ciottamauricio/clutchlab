import { useState } from 'react'
import { updateProfile, PLAYER_ROLES, GEAR_FIELDS } from './api'
import ChangePasswordModal from './ChangePasswordModal'
import { t } from '../../lib/i18n'

// Edit form for the caller's own profile. Seeded from the current user; on save it patches
// and hands the updated user back so the shell can refresh. The password change lives here
// too — one place to manage your own account.
export default function ProfileForm({ user, onSaved, onCancel }) {
  const [form, setForm] = useState({
    name: user.name ?? '',
    player_role: user.player_role ?? '',
    bio: user.bio ?? '',
    ...Object.fromEntries(GEAR_FIELDS.map(({ key }) => [key, user.gear?.[key] ?? ''])),
  })
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState(null)
  const [showPassword, setShowPassword] = useState(false)

  const set = (key) => (e) => setForm((f) => ({ ...f, [key]: e.target.value }))

  const submit = async (e) => {
    e.preventDefault()
    setSaving(true)
    setError(null)
    try {
      await updateProfile(form)
      await onSaved()
    } catch (err) {
      setError(err.code ?? 'error.unknown')
      setSaving(false)
    }
  }

  return (
    <form className="pf-card pf-form" onSubmit={submit}>
      <div className="pf-field">
        <label htmlFor="pf-name">Display name</label>
        <input id="pf-name" value={form.name} onChange={set('name')} required />
      </div>

      <div className="pf-field">
        <label htmlFor="pf-role">In-game role</label>
        <select id="pf-role" value={form.player_role} onChange={set('player_role')}>
          <option value="">—</option>
          {PLAYER_ROLES.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
        </select>
      </div>

      <div className="pf-field">
        <label htmlFor="pf-bio">Bio</label>
        <textarea id="pf-bio" value={form.bio} onChange={set('bio')} rows={3} maxLength={500} placeholder="A line about your game." />
      </div>

      <fieldset className="pf-gear-edit">
        <legend>Setup</legend>
        {GEAR_FIELDS.map(({ key, label }) => (
          <div className="pf-field" key={key}>
            <label htmlFor={`pf-${key}`}>{label}</label>
            <input id={`pf-${key}`} value={form[key]} onChange={set(key)} placeholder={label} />
          </div>
        ))}
      </fieldset>

      {error && <p className="error">{t(error)}</p>}

      <div className="pf-actions">
        <button type="submit" disabled={saving}>{saving ? 'Saving…' : 'Save profile'}</button>
        <button type="button" className="link-btn" onClick={onCancel} disabled={saving}>Cancel</button>
      </div>

      <div className="pf-security">
        <div>
          <span className="pf-security-title">Password</span>
          <span className="muted">Change the password you use to sign in.</span>
        </div>
        <button type="button" className="link-btn" onClick={() => setShowPassword(true)}>Change password</button>
      </div>

      {showPassword && <ChangePasswordModal onClose={() => setShowPassword(false)} />}
    </form>
  )
}
