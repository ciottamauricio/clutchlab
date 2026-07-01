import StatusBadge from './StatusBadge'

export default function MatchList({ matches, selectedId, onSelect }) {
  if (!matches.length) {
    return <p className="muted">No demos yet — upload one above.</p>
  }

  return (
    <ul className="match-list">
      {matches.map((match) => (
        <li key={match.id} className={match.id === selectedId ? 'active' : ''}>
          <button type="button" onClick={() => onSelect(match.id)}>
            <span className="file">{match.original_filename}</span>
            {match.map_name && <span className="map">{match.map_name}</span>}
            <StatusBadge status={match.status} />
          </button>
        </li>
      ))}
    </ul>
  )
}
