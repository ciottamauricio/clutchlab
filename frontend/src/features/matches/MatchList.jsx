import StatusBadge from './StatusBadge'
import { formatMatchDate, matchTeams } from './format'

function MatchCard({ match }) {
  const date = formatMatchDate(match.created_at)

  if (match.status !== 'parsed') {
    return (
      <>
        <span className="mc-line">
          <span className="mc-file">{match.original_filename}</span>
          <StatusBadge status={match.status} />
        </span>
        {date && <span className="mc-date">{date}</span>}
      </>
    )
  }

  const [a, b] = matchTeams(match)
  return (
    <>
      <span className="mc-line">
        <span className="mc-map">{match.map_name || 'unknown map'}</span>
        <span className="mc-date">{date}</span>
      </span>
      <span className="mc-score">
        <span className="mc-team">{a.name}</span>
        <span className="mc-nums">{a.score} <span className="mc-x">×</span> {b.score}</span>
        <span className="mc-team mc-team-right">{b.name}</span>
      </span>
    </>
  )
}

export default function MatchList({ matches, selectedId, onSelect, onDelete, deletingId }) {
  if (!matches.length) {
    return <p className="muted">No demos yet — upload one above.</p>
  }

  return (
    <ul className="match-list">
      {matches.map((match) => (
        <li key={match.id} className={match.id === selectedId ? 'active' : ''}>
          <button type="button" className="match-open" onClick={() => onSelect(match.id)}>
            <MatchCard match={match} />
          </button>
          <button
            type="button"
            className="match-delete"
            title="Delete match"
            aria-label="Delete match"
            disabled={deletingId === match.id}
            onClick={() => onDelete(match)}
          >
            ✕
          </button>
        </li>
      ))}
    </ul>
  )
}
