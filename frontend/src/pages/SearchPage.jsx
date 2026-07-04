import { useState } from 'react'
import { useSearch, useClutchSizes } from '../features/search/api'
import KillsTable from '../features/search/KillsTable'
import RoundsTable from '../features/search/RoundsTable'
import WeaponSelect from '../features/search/WeaponSelect'
import { t } from '../lib/i18n'

const SIDES = ['', 'CT', 'T']
const BUYS = ['', 'eco', 'force', 'full']
const REASONS = ['', 'elimination', 'bomb_exploded', 'bomb_defused', 'time']

export default function SearchPage() {
  const [kind, setKind] = useState('kills')
  const [lastKind, setLastKind] = useState('kills')
  const [q, setQ] = useState('')
  const [kf, setKf] = useState({ weapon: '', side: '', map: '', clutch: '', headshot: false, opening: false })
  const [rf, setRf] = useState({ winner: '', reason: '', map: '', ct_alive: '', t_alive: '', ct_buy: '', t_buy: '' })
  const { hits, total, loading, error, ran, run } = useSearch()
  const clutchSizes = useClutchSizes()

  const submit = (e) => {
    e.preventDefault()
    setLastKind(kind)
    run(kind, kind === 'kills' ? { q, ...kf } : { q, ...rf })
  }

  return (
    <>
      <div className="search-modes">
        <button type="button" className={kind === 'kills' ? 'active' : ''} onClick={() => setKind('kills')}>Kills</button>
        <button type="button" className={kind === 'rounds' ? 'active' : ''} onClick={() => setKind('rounds')}>Rounds</button>
      </div>

      <form className="search-form" onSubmit={submit}>
        <input placeholder="search…" value={q} onChange={(e) => setQ(e.target.value)} />

        {kind === 'kills' ? (
          <>
            <WeaponSelect value={kf.weapon} onChange={(v) => setKf({ ...kf, weapon: v })} />
            <select value={kf.side} onChange={(e) => setKf({ ...kf, side: e.target.value })}>
              {SIDES.map((s) => <option key={s} value={s}>{s || 'any side'}</option>)}
            </select>
            <input placeholder="map" value={kf.map} onChange={(e) => setKf({ ...kf, map: e.target.value })} />
            {clutchSizes.length > 0 && (
              <select value={kf.clutch} onChange={(e) => setKf({ ...kf, clutch: e.target.value })}>
                <option value="">any clutch</option>
                {clutchSizes.map((n) => <option key={n} value={n}>1v{n}</option>)}
              </select>
            )}
            <label className="chk"><input type="checkbox" checked={kf.headshot} onChange={(e) => setKf({ ...kf, headshot: e.target.checked })} /> HS</label>
            <label className="chk"><input type="checkbox" checked={kf.opening} onChange={(e) => setKf({ ...kf, opening: e.target.checked })} /> opening</label>
          </>
        ) : (
          <>
            <select value={rf.winner} onChange={(e) => setRf({ ...rf, winner: e.target.value })}>
              {SIDES.map((s) => <option key={s} value={s}>{s || 'any winner'}</option>)}
            </select>
            <select value={rf.reason} onChange={(e) => setRf({ ...rf, reason: e.target.value })}>
              {REASONS.map((s) => <option key={s} value={s}>{s || 'any reason'}</option>)}
            </select>
            <input placeholder="map" value={rf.map} onChange={(e) => setRf({ ...rf, map: e.target.value })} />
            <input type="number" placeholder="CT alive" value={rf.ct_alive} onChange={(e) => setRf({ ...rf, ct_alive: e.target.value })} />
            <input type="number" placeholder="T alive" value={rf.t_alive} onChange={(e) => setRf({ ...rf, t_alive: e.target.value })} />
            <select value={rf.ct_buy} onChange={(e) => setRf({ ...rf, ct_buy: e.target.value })}>
              {BUYS.map((s) => <option key={s} value={s}>{s || 'CT buy'}</option>)}
            </select>
            <select value={rf.t_buy} onChange={(e) => setRf({ ...rf, t_buy: e.target.value })}>
              {BUYS.map((s) => <option key={s} value={s}>{s || 'T buy'}</option>)}
            </select>
          </>
        )}

        <button type="submit" disabled={loading}>{loading ? 'Searching…' : 'Search'}</button>
      </form>

      {error && <p className="error">{t(error)}</p>}
      {ran && !error && <p className="muted">{total} result{total === 1 ? '' : 's'}</p>}

      {lastKind === 'kills' ? <KillsTable hits={hits} /> : <RoundsTable hits={hits} />}
    </>
  )
}
