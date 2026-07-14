import { useTrainings } from '../features/trainings/api'
import TrainingForm from '../features/trainings/TrainingForm'
import TrainingCard from '../features/trainings/TrainingCard'
import { t } from '../lib/i18n'

// Team practice schedule: upcoming sessions first, history below. The API returns
// all sessions of the caller's teams in chronological order; the split is "now".
export default function TrainingsPage() {
  const { trainings, error, loading, refresh } = useTrainings()

  const now = Date.now()
  const upcoming = trainings.filter((s) => new Date(s.scheduled_at).getTime() >= now)
  const past = trainings.filter((s) => new Date(s.scheduled_at).getTime() < now).reverse()

  return (
    <section className="trainings">
      <div className="tr-head">
        <h2>Trainings</h2>
        <TrainingForm onCreated={refresh} />
      </div>

      {error && <p className="error">{t(error)}</p>}
      {loading && <p className="muted">Loading…</p>}

      {!loading && trainings.length === 0 && (
        <p className="muted">No trainings scheduled — book the first practice above.</p>
      )}

      {upcoming.length > 0 && (
        <>
          <h3 className="tr-section">Upcoming</h3>
          <ul className="tr-list">
            {upcoming.map((s) => <TrainingCard key={s.id} training={s} onChanged={refresh} />)}
          </ul>
        </>
      )}

      {past.length > 0 && (
        <>
          <h3 className="tr-section">Past</h3>
          <ul className="tr-list">
            {past.map((s) => <TrainingCard key={s.id} training={s} onChanged={refresh} />)}
          </ul>
        </>
      )}
    </section>
  )
}
