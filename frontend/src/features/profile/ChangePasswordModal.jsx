import { useState } from 'react'
import { changePassword } from './api'
import { t } from '../../lib/i18n'

// Self-service password change: current password required, no email flow yet. On success
// every other session is signed out server-side — this one stays signed in.
export default function ChangePasswordModal({ onClose }) {
  const [current, setCurrent] = useState('')
  const [next, setNext] = useState('')
  const [confirm, setConfirm] = useState('')
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState(null)
  const [done, setDone] = useState(false)

  const submit = async (e) => {
    e.preventDefault()
    setSaving(true)
    setError(null)
    try {
      await changePassword({ current_password: current, password: next, password_confirmation: confirm })
      setDone(true)
    } catch (err) {
      setError(err.code ?? 'error.unknown')
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal" onClick={(e) => e.stopPropagation()}>
        <button className="modal-close" aria-label="Close" onClick={onClose}>✕</button>
        <h4 className="modal-title">Change password</h4>

        {done ? (
          <div className="cp-done">
            <p>Password changed. Any other signed-in devices have been signed out.</p>
            <button type="button" onClick={onClose}>Done</button>
          </div>
        ) : (
          <form className="cp-form" onSubmit={submit}>
            <label>
              Current password
              <input type="password" value={current} onChange={(e) => setCurrent(e.target.value)} autoComplete="current-password" required />
            </label>
            <label>
              New password
              <input type="password" value={next} onChange={(e) => setNext(e.target.value)} autoComplete="new-password" minLength={8} required />
            </label>
            <label>
              Confirm new password
              <input type="password" value={confirm} onChange={(e) => setConfirm(e.target.value)} autoComplete="new-password" minLength={8} required />
            </label>
            {error && <p className="error">{t(error)}</p>}
            <div className="cp-actions">
              <button type="submit" disabled={saving}>{saving ? 'Changing…' : 'Change password'}</button>
              <button type="button" className="link-btn" onClick={onClose} disabled={saving}>Cancel</button>
            </div>
          </form>
        )}
      </div>
    </div>
  )
}
