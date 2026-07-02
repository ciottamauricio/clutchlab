import { useMatch } from './api'
import { t } from '../../lib/i18n'
import Scoreboard from './Scoreboard'
import StatusBadge from './StatusBadge'

export default function MatchDashboard({ matchId }) {
  const { match, error } = useMatch(matchId)

  if (!matchId) return <p className="muted">Select a match to see its stats.</p>
  if (error) return <p className="error">{t(error)}</p>
  if (!match) return <p className="muted">Loading…</p>

  const inProgress = match.status !== 'parsed' && match.status !== 'failed'

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
            <span className="score">
              {match.ct_name || 'CT'} {match.score_ct} : {match.score_t} {match.t_name || 'T'}
            </span>
            <span className="rounds">{match.total_rounds} rounds</span>
          </div>
          <Scoreboard players={match.players} />
        </>
      )}
    </section>
  )
}
