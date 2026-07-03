import { useState } from 'react'
import { useSearch } from './api'
import KillsTable from './KillsTable'
import WeaponSelect from './WeaponSelect'
import { t } from '../../lib/i18n'

const SIDES = ['', 'CT', 'T']

// Kill search scoped to a single match. Unlike the global Search page, match_id is
// pinned here and the player/team pickers are drawn from this match, so you can ask
// "this team's AWP kills in this match" without free-text guessing. `killer_team` filters
// by the killer's whole-match team (stable across the side swap); `side` is the per-kill side.
export default function MatchSearch({ matchId, players, teams }) {
  const [f, setF] = useState({ killer_name: '', weapon: '', killer_team: '', side: '', headshot: false, opening: false })
  const { hits, total, loading, error, ran, run } = useSearch()

  const names = [...new Set(players.map((p) => p.name).filter(Boolean))].sort()

  const submit = (e) => {
    e.preventDefault()
    run('kills', { match_id: matchId, ...f })
  }

  return (
    <section className="match-search">
      <h3>Search kills in this match</h3>
      <form className="search-form" onSubmit={submit}>
        <select value={f.killer_name} onChange={(e) => setF({ ...f, killer_name: e.target.value })}>
          <option value="">any player</option>
          {names.map((n) => <option key={n} value={n}>{n}</option>)}
        </select>
        <WeaponSelect value={f.weapon} onChange={(v) => setF({ ...f, weapon: v })} />
        <select value={f.killer_team} onChange={(e) => setF({ ...f, killer_team: e.target.value })}>
          <option value="">any team</option>
          {teams.map((tm) => <option key={tm.side} value={tm.side}>{tm.name}</option>)}
        </select>
        <select value={f.side} onChange={(e) => setF({ ...f, side: e.target.value })}>
          {SIDES.map((s) => <option key={s} value={s}>{s || 'any side'}</option>)}
        </select>
        <label className="chk"><input type="checkbox" checked={f.headshot} onChange={(e) => setF({ ...f, headshot: e.target.checked })} /> HS</label>
        <label className="chk"><input type="checkbox" checked={f.opening} onChange={(e) => setF({ ...f, opening: e.target.checked })} /> opening</label>
        <button type="submit" disabled={loading}>{loading ? 'Searching…' : 'Search'}</button>
      </form>

      {error && <p className="error">{t(error)}</p>}
      {ran && !error && <p className="muted">{total} kill{total === 1 ? '' : 's'}</p>}
      <KillsTable hits={hits} />
    </section>
  )
}
