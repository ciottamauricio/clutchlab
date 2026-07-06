import { useState } from 'react'
import { useAwardKills } from './api'
import BodyHitgroups from '../../components/BodyHitgroups'
import WatchInGame from '../matches/WatchInGame'
import { WEAPONS } from '../search/weapons'
import { t } from '../../lib/i18n'

const WEAPON_LABEL = Object.fromEntries(WEAPONS.map((w) => [w.value, w.label]))

// The kills behind one award for one player. Same drill-down UX as the clutch dialog: each
// kill expands to its hitgroups + the in-game demo jump, and carries its own match/demo/tick.
export default function AwardKillsModal({ award, player, map, onClose }) {
  const { kills, loading, error } = useAwardKills(award.key, player.steam_id, map)
  const [openKey, setOpenKey] = useState(null)

  const toggle = (key) => setOpenKey((cur) => (cur === key ? null : key))

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal modal-wide" onClick={(e) => e.stopPropagation()}>
        <button className="modal-close" aria-label="Close" onClick={onClose}>✕</button>
        <h4 className="modal-title">
          <span aria-hidden="true">{award.emoji}</span> {award.label} <span className="muted">— {player.name}</span>
        </h4>

        {error && <p className="error">{t(error)}</p>}
        {loading && <p className="muted">Loading…</p>}
        {!loading && kills.length === 0 && <p className="muted">No kills to show.</p>}

        <ol className="clutch-kills award-kills">
          {kills.map((k, i) => {
            const key = `${k.match_id}-${k.tick}`
            return (
              <li key={key} className={openKey === key ? 'open' : ''}>
                <button type="button" className="ck-row" onClick={() => toggle(key)}>
                  <span className="ck-n">{i + 1}</span>
                  <span className="ck-victim">{k.killer_name} <span className="muted">→</span> {k.victim_name}</span>
                  <span className="ck-weapon">{WEAPON_LABEL[k.weapon] || k.weapon} · {k.map} r{k.round}{k.headshot ? ' • HS' : ''}</span>
                </button>
                {openKey === key && (
                  <div className="kill-expand-body ck-expand">
                    <BodyHitgroups hitgroups={k.hitgroups} />
                    <div className="kill-expand-watch">
                      <WatchInGame matchId={k.match_id} tick={k.tick} tickRate={k.tick_rate} demo={k.demo} killer={k.killer_name} />
                    </div>
                  </div>
                )}
              </li>
            )
          })}
        </ol>
      </div>
    </div>
  )
}
