import { NavLink, Outlet } from 'react-router-dom'
import { useAuth } from '../features/auth/AuthContext'
import ThemeToggle from './ThemeToggle'

// The authenticated app shell: branding, nav, current user + logout, then the page.
export default function AppLayout() {
  const { user, logout } = useAuth()

  return (
    <div className="app-shell">
      <header className="app-bar">
        <div className="app-bar-inner">
          <div className="brand">
            <img className="brand-mark" src="/favicon.svg" alt="" aria-hidden="true" />
            <span className="brand-word">Clutchlab</span>
          </div>
          <nav className="nav">
            <NavLink to="/" end>Matches</NavLink>
            <NavLink to="/teams">Teams</NavLink>
            <NavLink to="/tactics">Tactics</NavLink>
            <NavLink to="/search">Search</NavLink>
            <NavLink to="/awards">Awards</NavLink>
          </nav>
          <div className="user">
            <ThemeToggle />
            <span className="muted">{user?.name}</span>
            <button type="button" className="link-btn" onClick={logout}>Log out</button>
          </div>
        </div>
      </header>
      <main className="app-main">
        <div className="app-main-inner">
          <Outlet />
        </div>
      </main>
    </div>
  )
}
