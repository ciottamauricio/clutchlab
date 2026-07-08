import { useState } from 'react'
import { useTeamStats } from './api'
import PlayerClutchesModal from './PlayerClutchesModal'
import { t } from '../../lib/i18n'

// First day of the current month as YYYY-MM-DD — the default lower bound for the board.
function monthStart() {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-01`
}

// Roster stat board for the team's matches (Postgres kill_events), filtered to games played
// within [from, to]. `reloadKey` bumps when the roster changes so the board refetches.
export default function TeamStats({ teamId, reloadKey }) {
  const [from, setFrom] = useState(monthStart)
  const [to, setTo] = useState('')
  const { players, error, loading } = useTeamStats(teamId, reloadKey, from, to)
  const [clutchPlayer, setClutchPlayer] = useState(null)

  return (
    <div className="team-stats">
      <div className="board-head">
        <h3>Stat board</h3>
        <div className="board-filter">
          <label>From <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} /></label>
          <label>To <input type="date" value={to} onChange={(e) => setTo(e.target.value)} /></label>
          {from && (
            <button type="button" className="link-btn" onClick={() => { setFrom(''); setTo('') }}>All time</button>
          )}
        </div>
      </div>

      {error && <p className="error">{t(error)}</p>}
      {loading && players.length === 0 && <p className="muted">Crunching stats…</p>}
      {!loading && players.length === 0 && <p className="muted">No games in this range.</p>}

      {players.length > 0 && (
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
      )}

      {clutchPlayer && <PlayerClutchesModal player={clutchPlayer} onClose={() => setClutchPlayer(null)} />}
    </div>
  )
}
