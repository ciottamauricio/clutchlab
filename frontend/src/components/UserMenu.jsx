import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../features/auth/AuthContext'
import { useTheme } from '../lib/theme'

const GEAR_PATH = 'M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z'

// The account menu: gear + name in the top bar opens a dropdown (profile / theme / log out)
// instead of linking straight to a page — the modern "account menu" pattern. Editing and the
// password change both live on the profile page itself.
export default function UserMenu() {
  const { user, logout } = useAuth()
  const { theme, toggle } = useTheme()
  const navigate = useNavigate()
  const [open, setOpen] = useState(false)
  const rootRef = useRef(null)

  useEffect(() => {
    if (!open) return
    const onDown = (e) => {
      if (!rootRef.current?.contains(e.target)) setOpen(false)
    }
    const onKey = (e) => e.key === 'Escape' && setOpen(false)
    document.addEventListener('mousedown', onDown)
    document.addEventListener('keydown', onKey)
    return () => {
      document.removeEventListener('mousedown', onDown)
      document.removeEventListener('keydown', onKey)
    }
  }, [open])

  const go = (path) => () => {
    setOpen(false)
    navigate(path)
  }

  return (
    <div className="user-menu" ref={rootRef}>
      <button type="button" className="user-menu-trigger" onClick={() => setOpen((o) => !o)} aria-haspopup="menu" aria-expanded={open}>
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
          <circle cx="12" cy="12" r="3" />
          <path d={GEAR_PATH} />
        </svg>
        <span>{user?.name}</span>
      </button>

      {open && (
        <div className="user-menu-panel" role="menu">
          <button type="button" role="menuitem" onClick={go('/')}>Profile</button>
          <button type="button" role="menuitem" onClick={go('/study')}>The study</button>
          <button type="button" role="menuitemcheckbox" aria-checked={theme === 'dark'} onClick={toggle}>
            {theme === 'light' ? 'Dark mode' : 'Light mode'}
          </button>
          <div className="user-menu-divider" />
          <button type="button" role="menuitem" className="user-menu-danger" onClick={logout}>Log out</button>
        </div>
      )}
    </div>
  )
}
