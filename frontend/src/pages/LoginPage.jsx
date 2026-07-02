import { Link, Navigate } from 'react-router-dom'
import LoginForm from '../features/auth/LoginForm'
import { useAuth } from '../features/auth/AuthContext'

export default function LoginPage() {
  const { user, loading } = useAuth()
  if (!loading && user) return <Navigate to="/" replace />

  return (
    <div className="auth-page">
      <h1>Clutchlab</h1>
      <p className="tagline">Sign in to upload and review demos.</p>
      <LoginForm />
      <p className="muted">
        No account? <Link to="/register">Create one</Link>
      </p>
    </div>
  )
}
