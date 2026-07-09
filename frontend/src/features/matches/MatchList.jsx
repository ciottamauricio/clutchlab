import StatusBadge from './StatusBadge'
import { formatMatchDate, formatDuration, matchTeams, matchDate } from './format'

function MatchCard({ match }) {
  const date = formatMatchDate(matchDate(match))
  const duration = formatDuration(match.duration_seconds)

  const team = match.team?.name ? (
    <span className="mc-team-tag" title={match.uploaded_by ? `Uploaded by ${match.uploaded_by}` : undefined}>
      {match.team.name}
    </span>
  ) : null

  if (match.status !== 'parsed') {
    return (
      <>
        <span className="mc-line">
          <span className="mc-file">{match.original_filename}</span>
          <StatusBadge status={match.status} />
        </span>
        <span className="mc-line">
          {date && <span className="mc-date">{date}</span>}
          {team}
        </span>
      </>
    )
  }

  const [a, b] = matchTeams(match)
  return (
    <>
      <span className="mc-line">
        <span className="mc-map">{match.map_name || 'unknown map'}</span>
        <span className="mc-date">
          {date}
          {duration && <span className="mc-duration"> · {duration}</span>}
        </span>
      </span>
      {team && <span className="mc-line">{team}</span>}
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
          {match.can?.delete && (
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
          )}
        </li>
      ))}
    </ul>
  )
}
