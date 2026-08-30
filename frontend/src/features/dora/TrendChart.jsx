// Deploys vs. failures per day, as inline SVG — the same no-dependency approach the
// tactics board and topology diagram use.
//
// Bars are drawn against the busiest day in the window rather than a fixed ceiling, so a
// project doing 2 deploys a day and one doing 50 both produce a readable chart.
export default function TrendChart({ trend }) {
  if (!trend?.length) {
    return <p className="muted">No deploys recorded in this window.</p>
  }

  const width = 720
  const height = 160
  const pad = { top: 12, right: 12, bottom: 22, left: 28 }
  const plotW = width - pad.left - pad.right
  const plotH = height - pad.top - pad.bottom

  const peak = Math.max(1, ...trend.map((d) => d.deploys + d.failures))
  const slot = plotW / trend.length
  const barW = Math.max(2, Math.min(18, slot * 0.6))
  const y = (v) => plotH - (v / peak) * plotH

  return (
    <figure className="dora-trend">
      <svg viewBox={`0 0 ${width} ${height}`} role="img" aria-label="Deploys and failures per day">
        <g transform={`translate(${pad.left},${pad.top})`}>
          <line x1="0" y1={plotH} x2={plotW} y2={plotH} className="dora-axis" />
          <text x="-8" y={y(peak) + 4} textAnchor="end" className="dora-tick">{peak}</text>
          <text x="-8" y={plotH + 4} textAnchor="end" className="dora-tick">0</text>

          {trend.map((day, i) => {
            const x = i * slot + (slot - barW) / 2
            // Failures stack on top of deploys: the column is the day's total activity,
            // and the red segment is the part that went wrong.
            const okH = plotH - y(day.deploys)
            const badH = plotH - y(day.failures)
            return (
              <g key={day.date}>
                <rect x={x} y={y(day.deploys)} width={barW} height={okH} className="dora-bar-ok" />
                {day.failures > 0 && (
                  <rect
                    x={x}
                    y={y(day.deploys) - badH}
                    width={barW}
                    height={badH}
                    className="dora-bar-bad"
                  />
                )}
                <title>{`${day.date}: ${day.deploys} deploys, ${day.failures} failures`}</title>
              </g>
            )
          })}
        </g>
      </svg>
      <figcaption className="dora-legend">
        <span className="dora-key dora-key-ok" /> deploys
        <span className="dora-key dora-key-bad" /> failures
      </figcaption>
    </figure>
  )
}
