import { useState } from 'react'
import { useAuth } from './AuthContext'
import { t } from '../../lib/i18n'

export default function LoginForm() {
  const { login } = useAuth()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)

  const submit = async (e) => {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      await login(email, password)
    } catch (err) {
      setError(err.code ?? 'error.unknown')
      setBusy(false)
    }
  }

  return (
    <form className="auth-form" onSubmit={submit}>
      <label>
        Email
        <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} autoComplete="email" />
      </label>
      <label>
        Password
        <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} autoComplete="current-password" />
      </label>
      <button type="submit" disabled={busy}>{busy ? 'Signing in…' : 'Sign in'}</button>
      {error && <p className="error">{t(error)}</p>}
    </form>
  )
}
