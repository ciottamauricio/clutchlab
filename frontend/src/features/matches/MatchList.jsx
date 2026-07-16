import StatusBadge from './StatusBadge'
import { formatMatchDate, formatDuration, matchTeams, matchDate } from './format'

// The viewer's result, when the game was theirs (see matches.md rule 11). The letter
// carries the meaning, the color carries the mood — never color alone.
const RESULT_MARK = { win: 'W', loss: 'L', draw: 'D' }
const RESULT_WORD = { win: 'Won', loss: 'Lost', draw: 'Draw' }

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
  const result = match.viewer_result
  return (
    <>
      <span className="mc-line mc-head">
        <span className="mc-map-wrap">
          {result && (
            <span
              className={`mc-result mc-res-${result}`}
              title={RESULT_WORD[result]}
              aria-label={RESULT_WORD[result]}
            >
              {RESULT_MARK[result]}
            </span>
          )}
          <span className="mc-map">{match.map_name || 'unknown map'}</span>
        </span>
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

export default function MatchList({ matches, selectedId, onSelect, onDelete, deletingId, empty }) {
  if (!matches.length) {
    return <div className="muted match-empty">{empty ?? 'No demos yet — upload one above.'}</div>
  }

  return (
    <ul className="match-list">
      {matches.map((match) => (
        <li key={match.id} className={match.id === selectedId ? 'active' : ''}>
          <button
            type="button"
            className={`match-open${match.viewer_result ? ` res-${match.viewer_result}` : ''}`}
            onClick={() => onSelect(match.id)}
          >
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
