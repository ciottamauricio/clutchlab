import { Link, Navigate } from 'react-router-dom'
import LoginForm from '../features/auth/LoginForm'
import { useAuth } from '../features/auth/AuthContext'
import ThemeToggle from '../components/ThemeToggle'

export default function LoginPage() {
  const { user, loading } = useAuth()
  if (!loading && user) return <Navigate to="/" replace />

  return (
    <div className="auth-page">
      <div className="auth-topbar"><ThemeToggle /></div>
      <div className="auth-brand">
        <img className="auth-mark" src="/favicon.svg" alt="" aria-hidden="true" />
        <h1>Clutchlab</h1>
      </div>
      <p className="tagline">Sign in to upload and review demos.</p>
      <LoginForm />
      <p className="muted">
        No account? <Link to="/register">Create one</Link>
      </p>
    </div>
  )
}
