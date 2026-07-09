import { useState } from 'react'
import { useLocation } from 'react-router-dom'
import UploadDemo from '../features/matches/UploadDemo'
import MatchList from '../features/matches/MatchList'
import MatchDashboard from '../features/matches/MatchDashboard'
import { useMatches, useDeleteMatch } from '../features/matches/api'
import { t } from '../lib/i18n'

// Thin route: composes the matches feature, holds only UI state (which match is
// selected). Server state lives in the feature's hooks. A caller may deep-link a match
// (e.g. from the profile's recent form) by navigating here with { state: { matchId } }.
export default function DashboardPage() {
  const location = useLocation()
  const [selectedId, setSelectedId] = useState(location.state?.matchId ?? null)
  const { matches, refresh } = useMatches()
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
      <UploadDemo onUploaded={handleUploaded} />
      {deleteError && <p className="error">{t(deleteError)}</p>}
      <div className="layout">
        <aside>
          <h2>Matches</h2>
          <MatchList
            matches={matches}
            selectedId={selectedId}
            onSelect={setSelectedId}
            onDelete={handleDelete}
            deletingId={deletingId}
          />
        </aside>
        <main>
          <MatchDashboard matchId={selectedId} />
        </main>
      </div>
    </>
  )
}
