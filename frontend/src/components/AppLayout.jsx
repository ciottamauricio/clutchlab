import { NavLink, Outlet } from 'react-router-dom'
import { useAuth } from '../features/auth/AuthContext'
import ThemeToggle from './ThemeToggle'
import UserMenu from './UserMenu'

// The authenticated app shell: branding, nav, account menu, then the page.
export default function AppLayout() {
  const { user } = useAuth()

  return (
    <div className="app-shell">
      <header className="app-bar">
        <div className="app-bar-inner">
          <NavLink to="/" className="brand" title="Home">
            <img className="brand-mark" src="/favicon.svg" alt="" aria-hidden="true" />
            <span className="brand-word">Clutchlab</span>
          </NavLink>
          <nav className="nav">
            <NavLink to="/matches">Matches</NavLink>
            <NavLink to="/teams">Teams</NavLink>
            <NavLink to="/tactics">Tactics</NavLink>
            <NavLink to="/search">Search</NavLink>
            <NavLink to="/awards">Awards</NavLink>
            {user?.is_admin && <NavLink to="/admin">Admin</NavLink>}
          </nav>
          <div className="user">
            <ThemeToggle />
            <UserMenu />
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
