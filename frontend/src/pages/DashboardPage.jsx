import { useState } from 'react'
import UploadDemo from '../features/matches/UploadDemo'
import MatchList from '../features/matches/MatchList'
import MatchDashboard from '../features/matches/MatchDashboard'
import { useMatches } from '../features/matches/api'

// Thin route: composes the matches feature, holds only UI state (which match is
// selected). Server state lives in the feature's hooks.
export default function DashboardPage() {
  const [selectedId, setSelectedId] = useState(null)
  const { matches, refresh } = useMatches()

  const handleUploaded = (match) => {
    refresh()
    setSelectedId(match.id)
  }

  return (
    <div className="app">
      <header className="app-head">
        <h1>Clutchlab</h1>
        <p className="tagline">Upload a CS2 demo and watch it get parsed.</p>
      </header>

      <UploadDemo onUploaded={handleUploaded} />

      <div className="layout">
        <aside>
          <h2>Matches</h2>
          <MatchList matches={matches} selectedId={selectedId} onSelect={setSelectedId} />
        </aside>
        <main>
          <MatchDashboard matchId={selectedId} />
        </main>
      </div>
    </div>
  )
}
