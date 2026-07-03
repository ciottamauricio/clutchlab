import { useMemo, useState } from 'react'
import { useKillPositions } from './api'
import { hasRadar, radarUrl, toRadar } from './radar'
import WeaponSelect from '../search/WeaponSelect'
import { t } from '../../lib/i18n'

const SIDES = ['', 'CT', 'T']

// Kill heatmap: victim death locations plotted on the match's radar overview. Positions
// are fetched once and filtered here (api/docs/domains/heatmap.md).
export default function Heatmap({ matchId, players, teams }) {
  const { map, points, loading, error } = useKillPositions(matchId)
  const [f, setF] = useState({ killer_name: '', weapon: '', team: '', side: '', headshot: false })

  const names = [...new Set(players.map((p) => p.name).filter(Boolean))].sort()

  const shown = useMemo(
    () =>
      points.filter(
        (p) =>
          (!f.killer_name || p.killer_name === f.killer_name) &&
          (!f.weapon || p.weapon === f.weapon) &&
          (!f.team || p.team === f.team) &&
          (!f.side || p.side === f.side) &&
          (!f.headshot || p.headshot),
      ),
    [points, f],
  )

  if (error) return <section className="heatmap"><h3>Kill heatmap</h3><p className="error">{t(error)}</p></section>

  return (
    <section className="heatmap">
      <h3>Kill heatmap</h3>

      <div className="search-form">
        <select value={f.killer_name} onChange={(e) => setF({ ...f, killer_name: e.target.value })}>
          <option value="">any player</option>
          {names.map((n) => <option key={n} value={n}>{n}</option>)}
        </select>
        <WeaponSelect value={f.weapon} onChange={(v) => setF({ ...f, weapon: v })} />
        <select value={f.team} onChange={(e) => setF({ ...f, team: e.target.value })}>
          <option value="">any team</option>
          {teams.map((tm) => <option key={tm.side} value={tm.side}>{tm.name}</option>)}
        </select>
        <select value={f.side} onChange={(e) => setF({ ...f, side: e.target.value })}>
          {SIDES.map((s) => <option key={s} value={s}>{s || 'any side'}</option>)}
        </select>
        <label className="chk"><input type="checkbox" checked={f.headshot} onChange={(e) => setF({ ...f, headshot: e.target.checked })} /> HS only</label>
      </div>

      {loading && <p className="muted">Loading positions…</p>}
      {!loading && !hasRadar(map) && (
        <p className="muted">No radar available for {map || 'this map'}.</p>
      )}
      {!loading && hasRadar(map) && (
        <>
          <p className="muted">{shown.length} kill{shown.length === 1 ? '' : 's'} shown</p>
          <div className="radar" style={{ backgroundImage: `url(${radarUrl(map)})` }}>
            {shown.map((p, i) => {
              const pos = toRadar(map, p.x, p.y)
              if (!pos) return null
              return (
                <span
                  key={i}
                  className={`dot dot-${p.side || 'none'}${p.headshot ? ' dot-hs' : ''}`}
                  style={{ left: `${pos.left * 100}%`, top: `${pos.top * 100}%` }}
                  title={`${p.killer_name} → ${p.victim_name} (${p.weapon}, round ${p.round})`}
                />
              )
            })}
          </div>
        </>
      )}
    </section>
  )
}
