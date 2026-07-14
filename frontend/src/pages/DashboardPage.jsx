import { useState } from 'react'
import { useLocation } from 'react-router-dom'
import UploadDemo from '../features/matches/UploadDemo'
import MatchList from '../features/matches/MatchList'
import MatchDashboard from '../features/matches/MatchDashboard'
import { useMatches, useDeleteMatch } from '../features/matches/api'
import { useAuth } from '../features/auth/AuthContext'
import { t } from '../lib/i18n'

// Thin route: composes the matches feature, holds only UI state (which match is
// selected). Server state lives in the feature's hooks. A caller may deep-link a match
// (e.g. from the profile's recent form) by navigating here with { state: { matchId } }.
export default function DashboardPage() {
  const location = useLocation()
  const { user } = useAuth()
  const [selectedId, setSelectedId] = useState(location.state?.matchId ?? null)
  const [playerFilter, setPlayerFilter] = useState('')
  const { matches, refresh } = useMatches(3000, playerFilter)
  const { remove, deletingId, error: deleteError } = useDeleteMatch((id) => {
    refresh()
    setSelectedId((cur) => (cur === id ? null : cur))
  })

  const handleUploaded = (match) => {
    refresh()
    setSelectedId(match.id)
  }

  const handleDelete = (match) => {
    if (window.confirm(`Delete "${match.original_filename}"? This can't be undone.`)) {
      remove(match.id)
    }
  }

  return (
    <>
      {user?.can_upload && <UploadDemo onUploaded={handleUploaded} />}
      {deleteError && <p className="error">{t(deleteError)}</p>}
      <div className="layout">
        <aside>
          <h2>Matches</h2>
          <input
            type="search"
            className="match-filter"
            placeholder="Filter by player…"
            aria-label="Filter matches by player name"
            value={playerFilter}
            onChange={(e) => setPlayerFilter(e.target.value)}
          />
          <MatchList
            matches={matches}
            selectedId={selectedId}
            onSelect={setSelectedId}
            onDelete={handleDelete}
            deletingId={deletingId}
            filtered={playerFilter.trim() !== ''}
          />
        </aside>
        <main>
          <MatchDashboard matchId={selectedId} />
        </main>
      </div>
    </>
  )
}
