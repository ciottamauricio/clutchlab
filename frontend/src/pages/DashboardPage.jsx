import { useEffect, useState } from 'react'
import { useLocation } from 'react-router-dom'
import UploadDemo from '../features/matches/UploadDemo'
import MatchList from '../features/matches/MatchList'
import MatchDashboard from '../features/matches/MatchDashboard'
import { DateStrip, Pager } from '../features/matches/ArchiveNav'
import { currentMonth, monthLabel, stepMonth, dayLabel, stepDay } from '../features/matches/format'
import { useMatches, useDeleteMatch } from '../features/matches/api'
import AskAnalyst from '../features/analyst/AskAnalyst'
import { useAuth, useCan } from '../features/auth/AuthContext'
import { t } from '../lib/i18n'

// Thin route: composes the matches feature, holds only UI state (which match is
// selected, which month/page of the archive is open). Server state lives in the
// feature's hooks. A caller may deep-link a match (e.g. from the profile's recent
// form) by navigating here with { state: { matchId } }.
export default function DashboardPage() {
  const location = useLocation()
  const { user } = useAuth()
  const canAsk = useCan('search.use')
  const [selectedId, setSelectedId] = useState(location.state?.matchId ?? null)
  const [playerFilter, setPlayerFilter] = useState('')
  const [month, setMonth] = useState(currentMonth())
  const [day, setDay] = useState('')
  const [page, setPage] = useState(1)
  const { matches, meta, refresh } = useMatches(3000, playerFilter, month, day, page)
  const { remove, deletingId, error: deleteError } = useDeleteMatch((id) => {
    refresh()
    setSelectedId((cur) => (cur === id ? null : cur))
  })

  // Changing what's filtered restarts from the first page. A day always lives inside
  // its month, so picking or stepping a day keeps the month in sync (stepping from
  // Jul 1 back to Jun 30 moves the month too); changing the month leaves day view.
  const changeMonth = (m) => {
    setMonth(m)
    setDay('')
    setPage(1)
  }
  const changeDay = (d) => {
    setDay(d)
    if (d) setMonth(d.slice(0, 7))
    setPage(1)
  }
  const changeFilter = (value) => {
    setPlayerFilter(value)
    setPage(1)
  }

  // If the current page empties out (e.g. the last match on it was deleted), fall
  // back to the last page that still exists.
  useEffect(() => {
    if (meta && meta.last_page >= 1 && page > meta.last_page) setPage(meta.last_page)
  }, [meta, page])

  const handleUploaded = (match) => {
    refresh()
    setSelectedId(match.id)
  }

  const handleDelete = (match) => {
    if (window.confirm(`Delete "${match.original_filename}"? This can't be undone.`)) {
      remove(match.id)
    }
  }

  // Emptiness is direction: say which filter came up empty and offer the way out.
  const empty = playerFilter.trim()
    ? 'No matches with that player.'
    : day
      ? (
        <>
          Nothing played on {dayLabel(day)}.{' '}
          <button type="button" className="link-btn" onClick={() => changeDay(stepDay(day, -1))}>
            Try {dayLabel(stepDay(day, -1))}
          </button>
          {' or '}
          <button type="button" className="link-btn" onClick={() => changeDay('')}>back to {monthLabel(month)}</button>
        </>
      )
      : month
        ? (
          <>
            Nothing played in {monthLabel(month)}.{' '}
            <button type="button" className="link-btn" onClick={() => changeMonth(stepMonth(month, -1))}>
              Try {monthLabel(stepMonth(month, -1))}
            </button>
            {' or '}
            <button type="button" className="link-btn" onClick={() => changeMonth('')}>show all</button>
          </>
        )
        : 'No demos yet — upload one above.'

  return (
    <>
      {user?.can_upload && <UploadDemo onUploaded={handleUploaded} />}
      {canAsk && <AskAnalyst onOpenMatch={setSelectedId} />}
      {deleteError && <p className="error">{t(deleteError)}</p>}
      <div className="layout">
        <aside>
          <h2>Matches</h2>
          <input
            type="search"
            className="match-filter"
            placeholder="Filter by player…"
            aria-label="Filter matches by player name"
            value={playerFilter}
            onChange={(e) => changeFilter(e.target.value)}
          />
          <DateStrip
            month={month}
            day={day}
            matches={matches}
            onMonth={changeMonth}
            onDay={changeDay}
            total={meta?.total}
          />
          <MatchList
            matches={matches}
            selectedId={selectedId}
            onSelect={setSelectedId}
            onDelete={handleDelete}
            deletingId={deletingId}
            empty={empty}
          />
          <Pager meta={meta} page={page} onPage={setPage} />
        </aside>
        <main>
          <MatchDashboard matchId={selectedId} />
        </main>
      </div>
    </>
  )
}
