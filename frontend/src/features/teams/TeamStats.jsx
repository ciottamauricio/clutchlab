import { useState } from 'react'
import { useTeamStats } from './api'
import PlayerClutchesModal from './PlayerClutchesModal'
import { t } from '../../lib/i18n'

// Roster stat board, aggregated across all the caller's matches (Postgres kill_events).
// `reloadKey` bumps when the roster changes so the board refetches.
export default function TeamStats({ teamId, reloadKey }) {
  const { players, error, loading } = useTeamStats(teamId, reloadKey)
  const [clutchPlayer, setClutchPlayer] = useState(null)

  if (error) return <p className="error">{t(error)}</p>
  if (loading && players.length === 0) return <p className="muted">Crunching stats…</p>
  if (players.length === 0) return null

  return (
    <div className="team-stats">
      <h3>Stat board</h3>
      <div className="board-wrap">
        <table className="scoreboard">
          <thead>
            <tr>
              <th>Player</th>
              <th title="Matches this player appears in">Games</th>
              <th>K</th>
              <th>D</th>
              <th>K/D</th>
              <th>HS%</th>
              <th title="Opening (first) kills">Entry</th>
              <th title="Rounds this player died first">1st deaths</th>
              <th title="1vN rounds won — click to watch">Clutches</th>
            </tr>
          </thead>
          <tbody>
            {players.map((p) => (
              <tr key={p.steam_id}>
                <td>{p.name}</td>
                <td>{p.games}</td>
                <td>{p.kills}</td>
                <td>{p.deaths}</td>
                <td>{p.kd}</td>
                <td>{p.hs_pct}%</td>
                <td>{p.entry_kills}</td>
                <td>{p.first_deaths}</td>
                <td>
                  {p.clutches > 0 ? (
                    <button type="button" className="clutch-badge clutch-open" onClick={() => setClutchPlayer(p)}>
                      {p.clutches}
                    </button>
                  ) : '—'}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {clutchPlayer && <PlayerClutchesModal player={clutchPlayer} onClose={() => setClutchPlayer(null)} />}
    </div>
  )
}
