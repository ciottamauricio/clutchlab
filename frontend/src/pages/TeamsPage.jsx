import { useState } from 'react'
import { useTeams } from '../features/teams/api'
import CreateTeam from '../features/teams/CreateTeam'
import TeamDetail from '../features/teams/TeamDetail'

export default function TeamsPage() {
  const { teams, refresh } = useTeams()
  const [selectedId, setSelectedId] = useState(null)

  const onCreated = (team) => {
    refresh()
    setSelectedId(team.id)
  }

  return (
    <>
      <CreateTeam onCreated={onCreated} />
      <div className="layout">
        <aside>
          <h2>Your teams</h2>
          {teams.length === 0 ? (
            <p className="muted">No teams yet — create one above.</p>
          ) : (
            <ul className="match-list">
              {teams.map((team) => (
                <li key={team.id} className={team.id === selectedId ? 'active' : ''}>
                  <button type="button" onClick={() => setSelectedId(team.id)}>
                    <span className="file">{team.name}</span>
                    <span className="role">{team.my_role}</span>
                  </button>
                </li>
              ))}
            </ul>
          )}
        </aside>
        <main>
          <TeamDetail teamId={selectedId} />
        </main>
      </div>
    </>
  )
}
