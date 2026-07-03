import { WEAPON_GROUPS } from './weapons'

// Dropdown backed by the weapon registry. Empty value means "any weapon".
export default function WeaponSelect({ value, onChange }) {
  return (
    <select value={value} onChange={(e) => onChange(e.target.value)}>
      <option value="">any weapon</option>
      {WEAPON_GROUPS.map((g) => (
        <optgroup key={g.group} label={g.group}>
          {g.weapons.map((w) => (
            <option key={w.value} value={w.value}>{w.label}</option>
          ))}
        </optgroup>
      ))}
    </select>
  )
}
