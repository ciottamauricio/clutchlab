import { WEAPONS } from '../search/weapons'

const LABEL = Object.fromEntries(WEAPONS.map((w) => [w.value, w.label]))
const nameOf = (key) => LABEL[key] ?? key

// Top weapons by kills — a horizontal magnitude ranking. One measure, so one hue; each bar is
// directly labelled with its weapon and count (no legend needed).
export default function WeaponBreakdown({ weapons }) {
  if (!weapons || weapons.length === 0) return null

  const max = weapons[0].kills || 1

  return (
    <div className="pf-card">
      <h3>Top weapons</h3>
      <div className="wb-list">
        {weapons.map((w) => (
          <div className="wb-row" key={w.weapon}>
            <span className="wb-name">{nameOf(w.weapon)}</span>
            <div className="wb-track">
              <div className="wb-bar" style={{ width: `${(w.kills / max) * 100}%` }} />
            </div>
            <span className="wb-count">{w.kills}</span>
          </div>
        ))}
      </div>
    </div>
  )
}
