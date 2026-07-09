import { useNavigate } from 'react-router-dom'
import { formatMatchTime, formatDuration, mapLabel } from '../matches/format'

// Recent-form table: one row per recent match with the full readout — map, when it was played,
// how long it ran, kills–deaths, and a K/D value with an inline bar scaled against the 1.0
// break-even mark (the meaningful reference in CS). Each row is colored and lettered by
// outcome, so identity never rides on color alone. Each row also opens that match.
export default function RecentForm({ recent }) {
  const navigate = useNavigate()
  if (!recent || recent.length === 0) return null

  // Newest first for a table (the bar chart read oldest→newest left-to-right; a list reads top-down).
  const rows = [...recent].reverse()

  const openMatch = (id) => navigate('/matches', { state: { matchId: id } })

  // Scale bars so the 1.0 line always has headroom above it; cap outliers gently.
  const maxKd = Math.max(2, ...rows.map((m) => m.kd))
  const breakEven = (1 / maxKd) * 100 // where 1.0 sits on the shared scale
  const outcome = (won) => (won === true ? 'win' : won === false ? 'loss' : 'tie')
  const letter = (won) => (won === true ? 'W' : won === false ? 'L' : '–')

  const record = rows.reduce(
    (r, m) => { r[outcome(m.won)] += 1; return r },
    { win: 0, loss: 0, tie: 0 },
  )

  // Date + time as one field: "dd/mm/yy · HH:MM" (— when the match carries no played_at).
  const when = (iso) => {
    if (!iso) return '—'
    const d = new Date(iso)
    const pad = (n) => String(n).padStart(2, '0')
    const date = `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${String(d.getFullYear()).slice(2)}`
    return `${date} · ${formatMatchTime(iso)}`
  }

  return (
    <div className="pf-card rf-card">
      <div className="rf-head">
        <h3>Recent form</h3>
        <div className="rf-legend">
          <span className="rf-key rf-win">{record.win}W</span>
          <span className="rf-key rf-loss">{record.loss}L</span>
          <span className="muted">last {rows.length} matches</span>
        </div>
      </div>

      <div className="rf-scroll">
        <table className="rf-table">
          <thead>
            <tr>
              <th>Map</th>
              <th>Played</th>
              <th>Length</th>
              <th className="rf-num">K–D</th>
              <th className="rf-kd">K/D</th>
              <th className="rf-res">Res</th>
              <th className="rf-go" aria-label="Open match" />
            </tr>
          </thead>
          <tbody>
            {rows.map((m) => (
              <tr key={m.match_id}>
                <td className="rf-map" title={m.map || 'unknown'}>{mapLabel(m.map)}</td>
                <td className="rf-dim">{when(m.played_at)}</td>
                <td className="rf-dim">{formatDuration(m.duration_seconds) || '—'}</td>
                <td className="rf-num">{m.kills}–{m.deaths}</td>
                <td className="rf-kd">
                  <div className="rf-kd-cell">
                    <span className="rf-kd-val">{m.kd.toFixed(2)}</span>
                    <span className="rf-kd-track">
                      <span className="rf-kd-mark" style={{ left: `${breakEven}%` }} />
                      <span
                        className={`rf-kd-bar rf-${outcome(m.won)}`}
                        style={{ width: `${Math.min(100, (m.kd / maxKd) * 100)}%` }}
                      />
                    </span>
                  </div>
                </td>
                <td className={`rf-res rf-${outcome(m.won)}`}>{letter(m.won)}</td>
                <td className="rf-go">
                  <button
                    type="button"
                    className="rf-go-btn"
                    onClick={() => openMatch(m.match_id)}
                    title={`Open ${mapLabel(m.map)} on the Matches page`}
                  >
                    View →
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
