import { useNavigate } from 'react-router-dom'
import { updateTraining, deleteTraining } from './api'
import { MyPrep, RsvpButtons } from './TrainingPrep'
import { mapLabel, formatMatchTime } from '../matches/format'
import { radarUrl, hasRadar } from '../matches/radar'

// "In how long?" — the page's one urgent question, answered in words.
const countdown = (iso) => {
  const target = new Date(iso)
  const now = new Date()
  const days = Math.round((target.setHours(0, 0, 0, 0) - new Date(now).setHours(0, 0, 0, 0)) / 86400000)
  if (days <= 0) return 'today'
  if (days === 1) return 'tomorrow'
  if (days < 7) return `in ${days} days`
  return `in ${Math.round(days / 7)} week${days >= 11 ? 's' : ''}`
}

// The hero: the next practice, rendered like it matters — big time block, the map
// you'll drill bleeding through from the right (same committed-dark surface as the
// tactics board, so radars read in both themes). Everything after it stays quiet.
export default function NextTraining({ training, onChanged }) {
  const navigate = useNavigate()
  const when = new Date(training.scheduled_at)
  const day = when.toLocaleDateString(undefined, { weekday: 'long' })
  const date = when.toLocaleDateString(undefined, { day: '2-digit', month: 'short' })
  const heroMap = training.tactics?.find((tc) => hasRadar(tc.map))?.map

  return (
    <section
      className="tr-next"
      style={heroMap ? { '--tr-next-radar': `url(${radarUrl(heroMap)})` } : undefined}
    >
      <div className="tr-next-body">
        <span className="tr-next-eyebrow">Next practice · {countdown(training.scheduled_at)}</span>
        <div className="tr-next-when">
          <span className="tr-next-day">{day}</span>
          <span className="tr-next-time">{formatMatchTime(training.scheduled_at)}</span>
        </div>
        <div className="tr-next-title">{training.title}</div>
        <div className="tr-next-meta">
          {training.team?.name}
          <span aria-hidden="true"> · </span>{date}
          {training.duration_minutes ? <><span aria-hidden="true"> · </span>{training.duration_minutes}min</> : null}
        </div>

        {training.tactics?.length > 0 && (
          <div className="tr-next-tactics">
            {training.tactics.map((tc) => (
              <button
                key={tc.id}
                type="button"
                className="tr-next-tactic"
                title="Open on the tactics board"
                onClick={() => navigate('/tactics', { state: { tacticId: tc.id } })}
              >
                {tc.name}{tc.map ? ` · ${mapLabel(tc.map)}` : ''}
              </button>
            ))}
          </div>
        )}

        {training.players?.length > 0 && (
          <div className="tr-next-roster">
            {training.players.map((p) => (
              <span key={p.id} className={`tr-next-player rsvp-${p.rsvp ?? 'none'}`}>
                {p.rsvp === 'in' ? '✓ ' : p.rsvp === 'out' ? '✗ ' : ''}{p.name}
              </span>
            ))}
          </div>
        )}

        <MyPrep training={training} onChanged={onChanged} />
        <RsvpButtons training={training} onChanged={onChanged} />

        {training.can?.manage && (
          <div className="tr-next-actions">
            <button type="button" className="link-btn" onClick={async () => { await updateTraining(training.id, { canceled: true }); onChanged?.() }}>
              cancel
            </button>
            <button type="button" className="link-btn" onClick={async () => { await deleteTraining(training.id); onChanged?.() }}>
              delete
            </button>
          </div>
        )}
      </div>
    </section>
  )
}
