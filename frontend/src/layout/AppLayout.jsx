import { NavLink, Outlet } from 'react-router-dom'
import { useAuth, useCan } from '../features/auth/AuthContext'
import UserMenu from './UserMenu'

// The authenticated app shell: branding, nav, account menu, then the page.
export default function AppLayout() {
  const { user } = useAuth()
  const canTactics = useCan('tactics.view')
  const canSearch = useCan('search.use')
  const canAwards = useCan('awards.view')

  return (
    <div className="app-shell">
      <header className="app-bar">
        <div className="app-bar-inner">
          <NavLink to="/" className="brand" title="Home">
            <img className="brand-mark" src="/favicon.svg" alt="" aria-hidden="true" />
            <span className="brand-word">Clutchlab</span>
          </NavLink>
          <nav className="nav">
            <NavLink to="/" end>Profile</NavLink>
            <NavLink to="/matches">Matches</NavLink>
            <NavLink to="/teams">Teams</NavLink>
            <NavLink to="/trainings">Trainings</NavLink>
            {canTactics && <NavLink to="/tactics">Tactics</NavLink>}
            {canSearch && <NavLink to="/search">Search</NavLink>}
            {canAwards && <NavLink to="/awards">Awards</NavLink>}
            {user?.is_admin && <NavLink to="/admin">Admin</NavLink>}
            {user?.is_admin && <NavLink to="/dora">Delivery</NavLink>}
          </nav>
          <div className="user">
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
