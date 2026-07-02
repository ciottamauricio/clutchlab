import { useState } from 'react'
import { useAuth } from './AuthContext'
import { t } from '../../lib/i18n'

export default function RegisterForm() {
  const { register } = useAuth()
  const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '' })
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)

  const set = (k) => (e) => setForm((f) => ({ ...f, [k]: e.target.value }))

  const submit = async (e) => {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      await register(form)
    } catch (err) {
      setError(err.code ?? 'error.unknown')
      setBusy(false)
    }
  }

  return (
    <form className="auth-form" onSubmit={submit}>
      <label>
        Name
        <input value={form.name} onChange={set('name')} autoComplete="name" />
      </label>
      <label>
        Email
        <input type="email" value={form.email} onChange={set('email')} autoComplete="email" />
      </label>
      <label>
        Password
        <input type="password" value={form.password} onChange={set('password')} autoComplete="new-password" />
      </label>
      <label>
        Confirm password
        <input type="password" value={form.password_confirmation} onChange={set('password_confirmation')} autoComplete="new-password" />
      </label>
      <button type="submit" disabled={busy}>{busy ? 'Creating…' : 'Create account'}</button>
      {error && <p className="error">{t(error)}</p>}
    </form>
  )
}
