import { currentMonth, monthLabel, stepMonth, todayYmd, dayLabel, stepDay } from './format'

// The archive's two instruments: a scoreboard-style date strip (one click steps a
// month or a day — no calendar widget) and a pager that speaks chronology. The list
// is ordered most-recently-played first, so "newer / older" is true where
// "prev / next" is vague. The strip is a ladder: all time → month → day.

// `month` / `day` are '' when inactive. `matches` is the visible page — entering day
// view starts on the newest listed match's day, so it never opens on an empty evening.
export function DateStrip({ month, day, matches, onMonth, onDay, total }) {
  const enterDay = () => {
    const newest = matches?.[0]?.played_at
    if (newest) return onDay(newest.slice(0, 10))
    onDay(month === currentMonth() ? todayYmd() : `${month}-01`)
  }

  return (
    <div className="arch-months" role="group" aria-label="Filter matches by date">
      {day ? (
        <>
          <button type="button" className="arch-step" aria-label="Previous day" onClick={() => onDay(stepDay(day, -1))}>‹</button>
          <span className="arch-month arch-day">{dayLabel(day)}</span>
          <button type="button" className="arch-step" aria-label="Next day" onClick={() => onDay(stepDay(day, 1))}>›</button>
          <button type="button" className="arch-all" onClick={() => onDay('')}>month</button>
          <button type="button" className="arch-all" onClick={() => onMonth('')}>all</button>
        </>
      ) : month ? (
        <>
          <button type="button" className="arch-step" aria-label="Previous month" onClick={() => onMonth(stepMonth(month, -1))}>‹</button>
          <span className="arch-month">{monthLabel(month)}</span>
          <button type="button" className="arch-step" aria-label="Next month" onClick={() => onMonth(stepMonth(month, 1))}>›</button>
          <button type="button" className="arch-all" onClick={enterDay}>day</button>
          <button type="button" className="arch-all" onClick={() => onMonth('')}>all</button>
        </>
      ) : (
        <>
          <span className="arch-month">ALL TIME</span>
          <button type="button" className="arch-all" onClick={() => onMonth(currentMonth())}>this month</button>
        </>
      )}
      {typeof total === 'number' && (
        <span className="arch-total">{total} {total === 1 ? 'match' : 'matches'}</span>
      )}
    </div>
  )
}

export function Pager({ meta, page, onPage }) {
  if (!meta || meta.last_page <= 1) return null

  return (
    <nav className="arch-pager" aria-label="Match pages">
      <button type="button" disabled={page <= 1} onClick={() => onPage(page - 1)}>‹ newer</button>
      <span className="arch-page">{page} / {meta.last_page}</span>
      <button type="button" disabled={page >= meta.last_page} onClick={() => onPage(page + 1)}>older ›</button>
    </nav>
  )
}
