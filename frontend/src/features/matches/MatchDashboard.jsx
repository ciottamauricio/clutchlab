import { useMatch } from './api'
import { t } from '../../lib/i18n'
import Scoreboard from './Scoreboard'
import StatusBadge from './StatusBadge'
import MatchSearch from '../search/MatchSearch'

export default function MatchDashboard({ matchId }) {
  const { match, error } = useMatch(matchId)

  if (!matchId) return <p className="muted">Select a match to see its stats.</p>
  if (error) return <p className="error">{t(error)}</p>
  if (!match) return <p className="muted">Loading…</p>

  const inProgress = match.status !== 'parsed' && match.status !== 'failed'
  const players = match.players ?? []

  // Group the flat player list into the two rosters. Players arrive sorted by
  // kills, so each side stays sorted after filtering. Winner shown first.
  const teams = [
    { side: 'CT', title: match.ct_name || 'Counter-Terrorists', score: match.score_ct, players: players.filter((p) => p.team_side === 'CT') },
    { side: 'T', title: match.t_name || 'Terrorists', score: match.score_t, players: players.filter((p) => p.team_side === 'T') },
  ].sort((a, b) => (b.score ?? 0) - (a.score ?? 0))
  const unassigned = players.filter((p) => p.team_side !== 'CT' && p.team_side !== 'T')

  return (
    <section className="dashboard">
      <header className="dashboard-head">
        <h2>{match.original_filename}</h2>
        <StatusBadge status={match.status} />
      </header>

      {match.status === 'failed' && (
        <p className="error">{t(match.error_code ?? 'error.unknown')}</p>
      )}

      {inProgress && (
        <p className="muted">Parsing in progress — this updates automatically.</p>
      )}

      {match.status === 'parsed' && (
        <>
          <div className="summary">
            <span className="map">{match.map_name || 'unknown map'}</span>
            <span className="rounds">{match.total_rounds} rounds</span>
          </div>
          <div className="teams">
            {teams.map((team) => (
              <Scoreboard key={team.side} {...team} />
            ))}
            <Scoreboard title="Unassigned" side="" score={null} players={unassigned} />
          </div>
          <MatchSearch matchId={matchId} players={players} />
        </>
      )}
    </section>
  )
}
