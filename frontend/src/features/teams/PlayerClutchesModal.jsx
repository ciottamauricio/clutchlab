import { useState } from 'react'
import { usePlayerClutches } from './api'
import BodyHitgroups from '../../components/BodyHitgroups'
import WatchInGame from '../matches/WatchInGame'
import { t } from '../../lib/i18n'

// Dialog listing every clutch a rostered player won across your matches. Mirrors the match
// Clutches panel — kills in order, each expanding to its hitgroups + the in-game demo jump —
// but spans matches, so each card names its map and carries its own demo/tick for the jump.
export default function PlayerClutchesModal({ player, onClose }) {
  const { clutches, loading, error } = usePlayerClutches(player.steam_id)
  const [openKey, setOpenKey] = useState(null)

  const toggle = (key) => setOpenKey((cur) => (cur === key ? null : key))

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal modal-wide" onClick={(e) => e.stopPropagation()}>
        <button className="modal-close" aria-label="Close" onClick={onClose}>✕</button>
        <h4 className="modal-title">{player.name} <span className="muted">— clutches</span></h4>

        {error && <p className="error">{t(error)}</p>}
        {loading && <p className="muted">Loading…</p>}
        {!loading && clutches.length === 0 && <p className="muted">No clutches yet.</p>}

        <ul className="clutch-list">
          {clutches.map((c) => (
            <li key={`${c.match_id}-${c.round}`} className="clutch-card">
              <header className="clutch-head">
                <span className="clutch-badge">1v{c.size}</span>
                <span className="clutch-name">{c.map || 'unknown map'}</span>
                <span className="muted">round {c.round}</span>
                <span className="clutch-count muted">{c.kills.length}/{c.size} killed</span>
              </header>
              <ol className="clutch-kills">
                {c.kills.map((k, i) => {
                  const key = `${c.match_id}-${k.tick}`
                  return (
                    <li key={key} className={openKey === key ? 'open' : ''}>
                      <button type="button" className="ck-row" onClick={() => toggle(key)}>
                        <span className="ck-n">{i + 1}</span>
                        <span className="ck-victim">{k.victim_name}</span>
                        <span className="ck-weapon">{k.weapon}{k.headshot ? ' • HS' : ''}</span>
                      </button>
                      {openKey === key && (
                        <div className="kill-expand-body ck-expand">
                          <BodyHitgroups hitgroups={k.hitgroups} />
                          <div className="kill-expand-watch">
                            <WatchInGame matchId={c.match_id} tick={k.tick} tickRate={c.tick_rate} demo={c.demo} killer={c.killer_name} />
                          </div>
                        </div>
                      )}
                    </li>
                  )
                })}
              </ol>
            </li>
          ))}
        </ul>
      </div>
    </div>
  )
}
