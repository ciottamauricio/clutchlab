import { useUsers, useKnownPlayers } from '../features/admin/api'
import UserAdminList from '../features/admin/UserAdminList'
import { t } from '../lib/i18n'

export default function AdminPage() {
  const { users, error, loading, update } = useUsers()
  const players = useKnownPlayers()

  return (
    <section className="admin">
      <div className="admin-head">
        <h2>Admin</h2>
        <p className="muted">Manage every user's role and linked SteamID.</p>
      </div>

      {loading && <p className="muted">Loading…</p>}
      {error && <p className="error">{t(error)}</p>}
      {!loading && !error && <UserAdminList users={users} players={players} onUpdate={update} />}
    </section>
  )
}
