import { useTrainings } from '../features/trainings/api'
import TrainingForm from '../features/trainings/TrainingForm'
import TrainingCard from '../features/trainings/TrainingCard'
import NextTraining from '../features/trainings/NextTraining'
import { t } from '../lib/i18n'

// Team practice schedule. The page answers one question first — when's the next
// practice and what are we drilling — so the soonest live session renders as the
// hero; everything else stays quiet: remaining upcoming as cards, history dimmed.
export default function TrainingsPage() {
  const { trainings, error, loading, refresh } = useTrainings()

  const now = Date.now()
  const upcoming = trainings.filter((s) => new Date(s.scheduled_at).getTime() >= now)
  const past = trainings.filter((s) => new Date(s.scheduled_at).getTime() < now).reverse()
  const live = upcoming.filter((s) => !s.canceled_at)

  // The hero is the soonest session that's actually happening; a canceled one
  // stays in the ordinary list, struck through — that's information too.
  const next = upcoming.find((s) => !s.canceled_at)
  const rest = upcoming.filter((s) => s !== next)

  return (
    <section className="trainings">
      <header className="tr-masthead">
        <div className="tr-masthead-line">
          <span className="tr-eyebrow">Practice log</span>
          <TrainingForm onSaved={refresh} />
        </div>
        <h2 className="tr-masthead-title">Trainings</h2>
        <p className="tr-masthead-count">
          {live.length > 0
            ? <><strong>{live.length}</strong> upcoming</>
            : 'Nothing on the calendar'}
          <span aria-hidden="true"> · </span>
          <strong>{past.length}</strong> logged
        </p>
      </header>

      {error && <p className="error">{t(error)}</p>}
      {loading && <p className="muted">Loading…</p>}

      {!loading && trainings.length === 0 && (
        <p className="muted">No trainings scheduled — book the first practice above.</p>
      )}

      {next && <NextTraining training={next} onChanged={refresh} />}

      {rest.length > 0 && (
        <>
          <h3 className="tr-section">Also coming up</h3>
          <ul className="tr-list">
            {rest.map((s) => <TrainingCard key={s.id} training={s} onChanged={refresh} />)}
          </ul>
        </>
      )}

      {past.length > 0 && (
        <>
          <h3 className="tr-section">Past</h3>
          <ul className="tr-list tr-past">
            {past.map((s) => <TrainingCard key={s.id} training={s} onChanged={refresh} />)}
          </ul>
        </>
      )}
    </section>
  )
}
