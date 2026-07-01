import { useState } from 'react'
import UploadForm from './components/UploadForm'
import MatchList from './components/MatchList'
import MatchDashboard from './components/MatchDashboard'
import { useMatches } from './hooks/useMatches'
import './App.css'

export default function App() {
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

      <UploadForm onUploaded={handleUploaded} />

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
