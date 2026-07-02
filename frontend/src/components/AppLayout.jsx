import { NavLink, Outlet } from 'react-router-dom'
import { useAuth } from '../features/auth/AuthContext'

// The authenticated app shell: branding, nav, current user + logout, then the page.
export default function AppLayout() {
  const { user, logout } = useAuth()

  return (
    <div className="app">
      <header className="app-bar">
        <div className="brand">Clutchlab</div>
        <nav className="nav">
          <NavLink to="/" end>Matches</NavLink>
          <NavLink to="/teams">Teams</NavLink>
          <NavLink to="/tactics">Tactics</NavLink>
        </nav>
        <div className="user">
          <span className="muted">{user?.name}</span>
          <button type="button" className="link-btn" onClick={logout}>Log out</button>
        </div>
      </header>
      <main className="app-main">
        <Outlet />
      </main>
    </div>
  )
}
