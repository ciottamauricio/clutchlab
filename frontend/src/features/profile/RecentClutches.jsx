import { useState } from 'react'
import BodyHitgroups from '../../components/BodyHitgroups'
import WatchInGame from '../matches/WatchInGame'
import { formatMatchTime, mapLabel } from '../matches/format'

// The player's most recent clutches (rounds won while last alive), newest first. Each clutch
// expands to the kills that won it — hitgroups + a demo jump — the same drill-down as search.
// The clutcher is always the profile's owner, so their name labels the demo jump.
export default function RecentClutches({ clutches, playerName }) {
  // key of the open clutch (match-round), and the open kill within it (by tick).
  const [openId, setOpenId] = useState(null)
  const [openTick, setOpenTick] = useState(null)

  const dmy = (iso) => {
    if (!iso) return '—'
    const d = new Date(iso)
    const pad = (n) => String(n).padStart(2, '0')
    return `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${String(d.getFullYear()).slice(2)}`
  }

  const toggleClutch = (id) => {
    setOpenId((cur) => (cur === id ? null : id))
    setOpenTick(null)
  }
  const toggleKill = (tick) => setOpenTick((cur) => (cur === tick ? null : tick))

  return (
    <div className="pf-card rc-card">
      <div className="rf-head">
        <h3>Last clutches</h3>
        <span className="muted">rounds won last alive</span>
      </div>

      {(!clutches || clutches.length === 0) ? (
        <p className="muted rc-empty">No clutches yet — win a round as the last one standing and it shows up here.</p>
      ) : (
        <ul className="rc-list">
          {clutches.map((c) => {
            const id = `${c.match_id}-${c.round}`
            return (
              <li key={id} className={`rc-item${openId === id ? ' open' : ''}`}>
                <button type="button" className="rc-head-row" onClick={() => toggleClutch(id)} aria-expanded={openId === id}>
                  <span className="clutch-badge">1v{c.size}</span>
                  <span className="rc-map" title={c.map || 'unknown'}>{mapLabel(c.map)}</span>
                  <span className="rc-when">{dmy(c.played_at)} · {formatMatchTime(c.played_at) || '—'}</span>
                  <span className="rc-kills">{c.kills.length} {c.kills.length === 1 ? 'kill' : 'kills'}</span>
                  <span className="rc-caret" aria-hidden="true">{openId === id ? '▾' : '▸'}</span>
                </button>

                {openId === id && (
                  <ol className="clutch-kills rc-kills-list">
                    {c.kills.map((k, i) => (
                      <li key={k.tick} className={openTick === k.tick ? 'open' : ''}>
                        <button type="button" className="ck-row" onClick={() => toggleKill(k.tick)}>
                          <span className="ck-n">{i + 1}</span>
                          <span className="ck-victim">{k.victim_name}</span>
                          <span className="ck-weapon">{k.weapon}{k.headshot ? ' • HS' : ''}</span>
                        </button>
                        {openTick === k.tick && (
                          <div className="kill-expand-body ck-expand">
                            <BodyHitgroups hitgroups={k.hitgroups} />
                            <div className="kill-expand-watch">
                              <WatchInGame matchId={c.match_id} tick={k.tick} tickRate={c.tick_rate} demo={c.demo} killer={playerName} />
                            </div>
                          </div>
                        )}
                      </li>
                    ))}
                  </ol>
                )}
              </li>
            )
          })}
        </ul>
      )}
    </div>
  )
}
