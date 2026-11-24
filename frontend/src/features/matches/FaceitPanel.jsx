import { useRef, useState } from 'react'
import { useFaceitMatches, useUploadDemo, useUploadTeams } from './api'
import StatusBadge from './StatusBadge'
import { formatMatchDate, mapLabel } from './format'
import { t } from '../../lib/i18n'

// The letter carries the meaning, the color carries the mood — never color alone (as MatchList).
const RESULT_MARK = { win: 'W', loss: 'L' }
const RESULT_WORD = { win: 'Won', loss: 'Lost' }

// One FACEIT match. Missing: a link to its FACEIT room (where FACEIT offers the demo) and an upload
// that states the platform, the time and the FACEIT match itself, so nothing is asked again.
function FaceitRow({ match, teamId, onOpenMatch, onUploaded }) {
  const inputRef = useRef(null)
  const { upload, uploading, error } = useUploadDemo(onUploaded)
  const inClutchlab = match.clutchlab

  const pick = (e) => {
    const file = e.target.files?.[0]
    e.target.value = ''
    if (!file) return
    upload(file, {
      platform: 'faceit',
      playedAt: match.started_at,
      externalId: match.faceit_match_id,
      teamId: teamId || null,
    })
  }

  return (
    <li className="fc-row">
      <span className="fc-when">{formatMatchDate(match.started_at)}</span>
      <span className="fc-map">{mapLabel(match.map)}</span>
      <span className="fc-score">
        {match.result && (
          <span
            className={`mc-result mc-res-${match.result}`}
            title={RESULT_WORD[match.result]}
            aria-label={RESULT_WORD[match.result]}
          >
            {RESULT_MARK[match.result]}
          </span>
        )}
        {match.score ? `${match.score.own}–${match.score.opponent}` : '—'}
      </span>
      <span className="fc-actions">
        {inClutchlab ? (
          <>
            {inClutchlab.status !== 'parsed' && <StatusBadge status={inClutchlab.status} />}
            <button type="button" className="link-btn" onClick={() => onOpenMatch(inClutchlab.id)}>
              Open
            </button>
          </>
        ) : (
          <>
            {match.room_url && (
              <a className="fc-link" href={match.room_url} target="_blank" rel="noopener noreferrer">
                Get demo ↗
              </a>
            )}
            <input ref={inputRef} type="file" accept=".dem,.zst" hidden onChange={pick} />
            <button
              type="button"
              className="fc-btn"
              disabled={uploading}
              onClick={() => inputRef.current?.click()}
              title="Upload the demo you downloaded from FACEIT (.dem or .dem.zst)"
            >
              {uploading ? 'Uploading…' : 'Upload'}
            </button>
          </>
        )}
      </span>
      {error && <span className="error fc-error">{t(error)}</span>}
    </li>
  )
}

// The user's latest FACEIT matches and which are still missing from Clutchlab (spec 001). Starts
// folded to its one-line summary, which carries the missing count; Show lists them.
export default function FaceitPanel({ onOpenMatch, onUploaded }) {
  const { status, account, reason, matches, error, busy, refresh, reload } = useFaceitMatches()
  const { defaultTeamId } = useUploadTeams()
  const [open, setOpen] = useState(false)

  const missing = matches.filter((m) => !m.clutchlab).length
  const listed = status === 'ready' && account && matches.length > 0

  const handleUploaded = (match) => {
    reload()
    onUploaded?.(match)
  }

  let summary
  if (status === 'loading') summary = <span className="muted">Checking your FACEIT matches…</span>
  else if (status === 'off') summary = <span className="muted">{t('faceit.disabled')}</span>
  else if (reason) summary = <span className="muted">{t(reason)}</span>
  else if (account) {
    summary = (
      <span className="fc-summary">
        <strong>{account.nickname}</strong>
        {' · '}
        {matches.length === 0
          ? 'no finished matches yet'
          : missing > 0
            ? `${missing} of the last ${matches.length} not in Clutchlab yet`
            : `all of the last ${matches.length} are in Clutchlab`}
      </span>
    )
  }

  return (
    <section className="faceit" aria-label="FACEIT matches">
      <div className="fc-head">
        <span className="fc-label">FACEIT</span>
        {summary}
        {(status === 'ready' || status === 'error') && (
          <span className="fc-head-actions">
            <button type="button" className="link-btn" disabled={busy} onClick={refresh} title="Ask FACEIT again">
              {busy ? 'Checking…' : 'Refresh'}
            </button>
            {listed && (
              <button type="button" className="link-btn" onClick={() => setOpen(!open)} aria-expanded={open}>
                {open ? 'Hide' : 'Show'}
              </button>
            )}
          </span>
        )}
      </div>
      {error && <p className="error fc-note">{t(error)}</p>}
      {listed && open && (
        <ul className="fc-list">
          {matches.map((match) => (
            <FaceitRow
              key={match.faceit_match_id}
              match={match}
              teamId={defaultTeamId}
              onOpenMatch={onOpenMatch}
              onUploaded={handleUploaded}
            />
          ))}
        </ul>
      )}
    </section>
  )
}
