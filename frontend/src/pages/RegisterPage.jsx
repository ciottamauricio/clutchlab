import { Link, Navigate } from 'react-router-dom'
import RegisterForm from '../features/auth/RegisterForm'
import { useAuth } from '../features/auth/AuthContext'
import ThemeToggle from '../components/ThemeToggle'

export default function RegisterPage() {
  const { user, loading } = useAuth()
  if (!loading && user) return <Navigate to="/" replace />

  return (
    <div className="auth-page">
      <div className="auth-topbar"><ThemeToggle /></div>
      <div className="auth-brand">
        <img className="auth-mark" src="/favicon.svg" alt="" aria-hidden="true" />
        <h1>Clutchlab</h1>
      </div>
      <p className="tagline">Create an account to get started.</p>
      <RegisterForm />
      <p className="muted">
        Already have an account? <Link to="/login">Sign in</Link>
      </p>
    </div>
  )
}
