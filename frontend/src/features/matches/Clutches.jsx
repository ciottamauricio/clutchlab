import { useState } from 'react'
import { useClutches } from './api'
import BodyHitgroups from '../../components/BodyHitgroups'
import WatchInGame from './WatchInGame'
import { t } from '../../lib/i18n'

// Clutches in a match: each is a round the clutcher WON while last alive on their team,
// shown as their name + kills below in order. Click a kill for its hitgroups + demo jump.
export default function Clutches({ matchId }) {
  const { clutches, tickRate, demo, loading, error } = useClutches(matchId)
  const [size, setSize] = useState('')
  const [openTick, setOpenTick] = useState(null)

  if (error) return <section className="clutches"><h3>Clutches</h3><p className="error">{t(error)}</p></section>

  const sizes = [...new Set(clutches.map((c) => c.size))].sort((a, b) => a - b)
  const shown = size ? clutches.filter((c) => c.size === Number(size)) : clutches
  const toggle = (tick) => setOpenTick((cur) => (cur === tick ? null : tick))

  return (
    <section className="clutches">
      <h3>Clutches</h3>

      {!loading && clutches.length === 0 && <p className="muted">No clutches in this match.</p>}

      {clutches.length > 0 && (
        <>
          <div className="search-form">
            <select value={size} onChange={(e) => setSize(e.target.value)}>
              <option value="">any clutch</option>
              {sizes.map((n) => <option key={n} value={n}>1v{n}</option>)}
            </select>
            <span className="muted">{shown.length} clutch{shown.length === 1 ? '' : 'es'}</span>
          </div>

          <ul className="clutch-list">
            {shown.map((c) => (
              <li key={c.round} className="clutch-card">
                <header className="clutch-head">
                  <span className="clutch-badge">1v{c.size}</span>
                  <span className="clutch-name">{c.killer_name}</span>
                  <span className="muted">round {c.round}</span>
                  <span className="clutch-count muted">{c.kills.length}/{c.size} killed</span>
                </header>
                <ol className="clutch-kills">
                  {c.kills.map((k, i) => (
                    <li key={k.tick} className={openTick === k.tick ? 'open' : ''}>
                      <button type="button" className="ck-row" onClick={() => toggle(k.tick)}>
                        <span className="ck-n">{i + 1}</span>
                        <span className="ck-victim">{k.victim_name}</span>
                        <span className="ck-weapon">{k.weapon}{k.headshot ? ' • HS' : ''}</span>
                      </button>
                      {openTick === k.tick && (
                        <div className="kill-expand-body ck-expand">
                          <BodyHitgroups hitgroups={k.hitgroups} />
                          <div className="kill-expand-watch">
                            <WatchInGame matchId={matchId} tick={k.tick} tickRate={tickRate} demo={demo} killer={c.killer_name} killerSteamId={c.killer_steam_id} />
                          </div>
                        </div>
                      )}
                    </li>
                  ))}
                </ol>
              </li>
            ))}
          </ul>
        </>
      )}
    </section>
  )
}
