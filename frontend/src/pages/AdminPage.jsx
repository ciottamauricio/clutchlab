import { useUsers, useKnownPlayers } from '../features/admin/api'
import { useAuth } from '../features/auth/AuthContext'
import UserAdminList from '../features/admin/UserAdminList'
import PermissionMatrix from '../features/admin/PermissionMatrix'
import { t } from '../lib/i18n'

export default function AdminPage() {
  const { users, error, loading, update, remove } = useUsers()
  const players = useKnownPlayers()
  const { user } = useAuth()

  return (
    <section className="admin">
      <div className="admin-head">
        <h2>Admin</h2>
        <p className="muted">Manage every user's role and linked SteamID.</p>
      </div>

      {loading && <p className="muted">Loading…</p>}
      {error && <p className="error">{t(error)}</p>}
      {!loading && !error && (
        <UserAdminList users={users} players={players} currentUserId={user?.id} onUpdate={update} onDelete={remove} />
      )}

      <div className="admin-head admin-head-sub">
        <h2>Permissions</h2>
        <p className="muted">What each role can do. Changes take effect immediately.</p>
      </div>
      <PermissionMatrix />
    </section>
  )
}
