import { useTeamComparison } from './api'
import { t as tr } from '../../lib/i18n'

// The stats to line up, top to bottom. `invert` marks stats where lower is better (deaths),
// so the winning side is highlighted correctly.
const ROWS = [
  { key: 'score', label: 'Rounds won' },
  { key: 'opening_kills', label: 'Opening kills' },
  { key: 'kills', label: 'Kills' },
  { key: 'assists', label: 'Assists' },
  { key: 'hs_pct', label: 'Headshot %', suffix: '%' },
  { key: 'clutches', label: 'Clutches won' },
]

// Side-by-side CT vs T comparison for the match: each stat as two values with a split bar
// showing the balance, the leading side highlighted in its team colour.
export default function TeamComparison({ matchId }) {
  const { ct, t, error } = useTeamComparison(matchId)

  if (error) return <section className="compare"><h3>Team comparison</h3><p className="error">{tr(error)}</p></section>
  if (!ct || !t) return null

  return (
    <section className="compare">
      <h3>Team comparison</h3>
      <div className="cmp-heads">
        <span className="cmp-team cmp-ct-val">{ct.name}</span>
        <span className="cmp-team cmp-t-val">{t.name}</span>
      </div>
      <div className="cmp-rows">
        {ROWS.map((r) => {
          const cv = ct[r.key]
          const tv = t[r.key]
          const total = cv + tv
          const cPct = total ? (cv / total) * 100 : 50
          const cWin = r.invert ? cv < tv : cv > tv
          const tWin = r.invert ? tv < cv : tv > cv
          const suffix = r.suffix || ''
          return (
            <div className="cmp-row" key={r.key}>
              <span className={`cmp-val cmp-ct-val${cWin ? ' win' : ''}`}>{cv}{suffix}</span>
              <span className="cmp-label">{r.label}</span>
              <span className={`cmp-val cmp-t-val cmp-right${tWin ? ' win' : ''}`}>{tv}{suffix}</span>
              <div className="cmp-bar">
                <div className="cmp-bar-ct" style={{ width: `${cPct}%` }} />
                <div className="cmp-bar-t" style={{ width: `${100 - cPct}%` }} />
              </div>
            </div>
          )
        })}
      </div>
    </section>
  )
}
