import { formatMatchDate } from '../matches/format'

// Recent-form chart: one bar per recent match, height = K/D, colored by match outcome
// (win/loss/tie). A dashed line marks the 1.0 break-even — the meaningful reference in CS.
// Outcome is also spelled out as a W/L/– letter, so identity never rides on color alone.
export default function RecentForm({ recent }) {
  if (!recent || recent.length === 0) return null

  // Scale so the 1.0 line always has headroom above it; cap outliers gently.
  const maxKd = Math.max(2, ...recent.map((m) => m.kd))
  const baseline = (1 / maxKd) * 100
  const outcome = (won) => (won === true ? 'win' : won === false ? 'loss' : 'tie')
  const letter = (won) => (won === true ? 'W' : won === false ? 'L' : '–')

  const record = recent.reduce(
    (r, m) => { r[outcome(m.won)] += 1; return r },
    { win: 0, loss: 0, tie: 0 },
  )

  return (
    <div className="pf-card rf-card">
      <div className="rf-head">
        <h3>Recent form</h3>
        <div className="rf-legend">
          <span className="rf-key rf-win">{record.win}W</span>
          <span className="rf-key rf-loss">{record.loss}L</span>
          <span className="muted">K/D per match</span>
        </div>
      </div>

      <div className="rf-chart" role="img" aria-label={`K/D over the last ${recent.length} matches`}>
        <div className="rf-baseline" style={{ bottom: `${baseline}%` }}><span>1.0</span></div>
        {recent.map((m) => (
          <div className="rf-col" key={m.match_id}>
            <div className="rf-bar-track">
              <div
                className={`rf-bar rf-${outcome(m.won)}`}
                style={{ height: `${(m.kd / maxKd) * 100}%` }}
                title={`${m.map || 'unknown'} · ${formatMatchDate(m.played_at)} · ${letter(m.won)} · ${m.kd.toFixed(2)} K/D (${m.kills}–${m.deaths})`}
              />
            </div>
            <span className={`rf-tick rf-${outcome(m.won)}`}>{letter(m.won)}</span>
          </div>
        ))}
      </div>
    </div>
  )
}
