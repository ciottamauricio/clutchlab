import { useState } from 'react'
import { NADE_TYPES } from './nades'
import { mapLabel } from '../matches/format'

const ALL_MAPS = [
  'de_mirage', 'de_inferno', 'de_dust2', 'de_nuke', 'de_ancient',
  'de_anubis', 'de_overpass', 'de_train', 'de_vertigo',
]

const keyOf = (a) => `${a.user_id}:${a.map}:${a.nade_type}`

// Homework picked while scheduling. Mirrors the card's manager controls, but writes into
// the form's local `assignments` state instead of the API — the session doesn't exist yet.
// One row per rostered player; pick a map once, then tag nades onto whoever needs them.
export default function HomeworkPicker({ roster, tactics, assignments, onChange }) {
  const tacticMaps = [...new Set((tactics ?? []).map((tc) => tc.map).filter(Boolean))]
  const [map, setMap] = useState(tacticMaps[0] ?? 'de_mirage')

  if (roster.length === 0) return null

  const has = (a) => assignments.some((x) => keyOf(x) === keyOf(a))
  const toggle = (a) =>
    onChange(has(a) ? assignments.filter((x) => keyOf(x) !== keyOf(a)) : [...assignments, a])

  const mapOptions = [...new Set([map, ...tacticMaps, ...ALL_MAPS])]

  return (
    <div className="hw">
      <div className="hw-head">
        <span className="tr-picker-label">Homework (optional)</span>
        <label className="hw-map">
          on
          <select value={map} onChange={(e) => setMap(e.target.value)}>
            {mapOptions.map((m) => <option key={m} value={m}>{mapLabel(m)}</option>)}
          </select>
        </label>
      </div>

      <ul className="hw-rows">
        {roster.map((p) => (
          <li key={p.id} className="hw-row">
            <span className="hw-name">{p.name}</span>
            <span className="hw-chips">
              {NADE_TYPES.map((n) => {
                const a = { user_id: p.id, map, nade_type: n.value }
                const on = has(a)
                return (
                  <button
                    key={n.value}
                    type="button"
                    className={`hw-chip${on ? ' on' : ''}`}
                    aria-pressed={on}
                    title={`${mapLabel(map)} ${n.label.toLowerCase()} for ${p.name}`}
                    onClick={() => toggle(a)}
                  >
                    {n.short}
                  </button>
                )
              })}
            </span>
          </li>
        ))}
      </ul>
    </div>
  )
}
