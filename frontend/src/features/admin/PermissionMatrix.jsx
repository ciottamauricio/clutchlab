import { useState } from 'react'
import { usePermissionMatrix } from './api'
import { t } from '../../lib/i18n'

// The admin grant editor: one grid per scope (App abilities × global roles, Team abilities ×
// team roles). A checkbox is a live grant — toggling it saves that role's new ability set. The
// `admin` global role is shown but locked: the master admin bypasses every check by design.
export default function PermissionMatrix() {
  const { matrix, error, loading, setRole } = usePermissionMatrix()
  const [saving, setSaving] = useState(null) // `${scope}:${role}:${key}` in flight
  const [saveError, setSaveError] = useState(null)

  if (loading) return <p className="muted">Loading…</p>
  if (error) return <p className="error">{t(error)}</p>
  if (!matrix) return null

  const has = (scope, role, key) => (matrix.grants[scope]?.[role] ?? []).includes(key)

  const toggle = async (scope, role, key) => {
    const current = matrix.grants[scope]?.[role] ?? []
    const next = current.includes(key) ? current.filter((k) => k !== key) : [...current, key]
    const cellId = `${scope}:${role}:${key}`
    setSaving(cellId)
    setSaveError(null)
    try {
      await setRole(scope, role, next)
    } catch (e) {
      setSaveError(e.code ?? 'error.unknown')
    } finally {
      setSaving(null)
    }
  }

  const abilityRow = (scope, roles, a) => (
    <tr key={a.key}>
      <td className="perm-ability">
        <span className="perm-label">{a.label}</span>
        <span className="perm-desc muted">{a.description}</span>
      </td>
      {roles.map((role) => {
        const locked = scope === 'app' && role === 'admin'
        const cellId = `${scope}:${role}:${a.key}`
        return (
          <td key={role} className="perm-cell">
            <input
              type="checkbox"
              checked={locked ? true : has(scope, role, a.key)}
              disabled={locked || saving === cellId}
              onChange={() => toggle(scope, role, a.key)}
              aria-label={`${role}: ${a.label}`}
            />
          </td>
        )
      })}
    </tr>
  )

  const grid = (scope, title, note) => {
    const abilities = matrix.abilities.filter((a) => a.scope === scope)
    const roles = matrix.roles[scope]
    // Group by area, preserving the catalog's order of first appearance.
    const areas = [...new Set(abilities.map((a) => a.area))]
    return (
      <div className="perm-grid-wrap">
        <div className="perm-grid-head">
          <h3>{title}</h3>
          <span className="muted">{note}</span>
        </div>
        <div className="perm-scroll">
          <table className="perm-table">
            <thead>
              <tr>
                <th className="perm-ability">Ability</th>
                {roles.map((role) => <th key={role} className="perm-role">{role}</th>)}
              </tr>
            </thead>
            {areas.map((area) => (
              <tbody key={area}>
                <tr className="perm-area-row">
                  <th className="perm-area" colSpan={roles.length + 1}>{area}</th>
                </tr>
                {abilities.filter((a) => a.area === area).map((a) => abilityRow(scope, roles, a))}
              </tbody>
            ))}
          </table>
        </div>
      </div>
    )
  }

  return (
    <div className="perm-matrix">
      {saveError && <p className="error">{t(saveError)}</p>}
      {grid('team', 'Team abilities', 'Per team role — resolved against the match or team in question.')}
      {grid('app', 'App abilities', 'Per global role — whole pages. Admin bypasses every check.')}
    </div>
  )
}
