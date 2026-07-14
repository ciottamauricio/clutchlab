import { useNavigate } from 'react-router-dom'
import { updateTraining, deleteTraining } from './api'
import { mapLabel, formatMatchTime } from '../matches/format'

const dayLabel = (iso) => {
  const d = new Date(iso)
  return d.toLocaleDateString(undefined, { weekday: 'short', day: '2-digit', month: 'short' })
}

// One session: when, what, who — tactics deep-link to the board. Cancel keeps the
// record (a struck-through session tells a story); delete removes it.
export default function TrainingCard({ training, onChanged }) {
  const navigate = useNavigate()
  const canceled = Boolean(training.canceled_at)

  const toggleCancel = async () => {
    await updateTraining(training.id, { canceled: !canceled })
    onChanged?.()
  }

  const remove = async () => {
    await deleteTraining(training.id)
    onChanged?.()
  }

  return (
    <li className={`tr-card${canceled ? ' tr-canceled' : ''}`}>
      <div className="tr-when">
        <span className="tr-day">{dayLabel(training.scheduled_at)}</span>
        <span className="tr-time">
          {formatMatchTime(training.scheduled_at)}
          {training.duration_minutes ? ` · ${training.duration_minutes}min` : ''}
        </span>
      </div>

      <div className="tr-body">
        <div className="tr-title-row">
          <span className="tr-title">{training.title}</span>
          <span className="tr-team">{training.team?.name}</span>
          {canceled && <span className="tr-canceled-tag">canceled</span>}
        </div>

        {training.tactics?.length > 0 && (
          <div className="tr-tactics">
            {training.tactics.map((tc) => (
              <button
                key={tc.id}
                type="button"
                className="tr-tactic-link"
                title="Open on the tactics board"
                onClick={() => navigate('/tactics', { state: { tacticId: tc.id } })}
              >
                {tc.name}{tc.map ? ` · ${mapLabel(tc.map)}` : ''}
              </button>
            ))}
          </div>
        )}

        {training.players?.length > 0 && (
          <p className="tr-roster">{training.players.map((p) => p.name).join(' · ')}</p>
        )}
        {training.notes && <p className="tr-notes-text">{training.notes}</p>}
      </div>

      {training.can?.manage && (
        <div className="tr-actions">
          <button type="button" className="link-btn" onClick={toggleCancel}>
            {canceled ? 'restore' : 'cancel'}
          </button>
          <button type="button" className="link-btn" onClick={remove}>delete</button>
        </div>
      )}
    </li>
  )
}
