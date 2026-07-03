import { useMatch, useReparseMatch } from './api'
import { t } from '../../lib/i18n'
import Scoreboard from './Scoreboard'
import StatusBadge from './StatusBadge'
import Heatmap from './Heatmap'
import MatchSearch from '../search/MatchSearch'
import { formatMatchDate, matchTeams } from './format'

export default function MatchDashboard({ matchId }) {
  const { match, error, reload } = useMatch(matchId)
  const { reparse, reparsing, error: reparseError } = useReparseMatch(reload)

  if (!matchId) return <p className="muted">Select a match to see its stats.</p>
  if (error) return <p className="error">{t(error)}</p>
  if (!match) return <p className="muted">Loading…</p>

  const inProgress = match.status !== 'parsed' && match.status !== 'failed'
  const parsed = match.status === 'parsed'
  const players = match.players ?? []
  const date = formatMatchDate(match.created_at)

  // Group the flat player list into the two rosters. Players arrive sorted by
  // kills, so each side stays sorted after filtering. Winner shown first.
  const teams = [
    { side: 'CT', title: match.ct_name || 'Counter-Terrorists', score: match.score_ct, players: players.filter((p) => p.team_side === 'CT') },
    { side: 'T', title: match.t_name || 'Terrorists', score: match.score_t, players: players.filter((p) => p.team_side === 'T') },
  ].sort((a, b) => (b.score ?? 0) - (a.score ?? 0))
  const unassigned = players.filter((p) => p.team_side !== 'CT' && p.team_side !== 'T')
  const [teamA, teamB] = matchTeams(match)

  return (
    <section className="dashboard">
      <header className="dashboard-head">
        <div className="dh-title">
          <h2>{parsed ? (match.map_name || 'unknown map') : match.original_filename}</h2>
          <div className="dh-meta">
            {date && <span>{date}</span>}
            {parsed && <span>{match.total_rounds} rounds</span>}
          </div>
        </div>
        <div className="dh-actions">
          <StatusBadge status={match.status} />
          {!inProgress && (
            <button type="button" className="link-btn" disabled={reparsing} onClick={() => reparse(matchId)}>
              {reparsing ? 'Re-parsing…' : 'Re-parse'}
            </button>
          )}
        </div>
      </header>

      {reparseError && <p className="error">{t(reparseError)}</p>}

      {match.status === 'failed' && (
        <p className="error">{t(match.error_code ?? 'error.unknown')}</p>
      )}

      {inProgress && (
        <p className="muted">Parsing in progress — this updates automatically.</p>
      )}

      {parsed && (
        <>
          <div className="teams">
            {teams.map((team) => (
              <Scoreboard key={team.side} {...team} />
            ))}
            <Scoreboard title="Unassigned" side="" score={null} players={unassigned} />
          </div>
          <p className="demo-file">{match.original_filename}</p>
          <Heatmap matchId={matchId} players={players} teams={[teamA, teamB]} />
          <MatchSearch matchId={matchId} players={players} teams={[teamA, teamB]} />
        </>
      )}
    </section>
  )
}
