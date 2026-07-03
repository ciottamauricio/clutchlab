// Body hitgroup map for a single kill: the CS2 silhouette (a themeable mask over
// public/hitgroups/body.png) with a damage badge on each body zone the killer hit.
// `hitgroups` is { head, chest, stomach, arms, legs } → health damage (any may be absent).
const ZONES = [
  { key: 'head', label: 'Head', top: 7, left: 50 },
  { key: 'chest', label: 'Chest', top: 26, left: 50 },
  { key: 'stomach', label: 'Stomach', top: 37, left: 50 },
  { key: 'arms', label: 'Arms', top: 30, left: 19 },
  { key: 'legs', label: 'Legs', top: 66, left: 50 },
]

export default function BodyHitgroups({ hitgroups }) {
  const hg = hitgroups || {}
  const values = Object.values(hg)
  const total = values.reduce((a, b) => a + b, 0)
  const max = Math.max(1, ...values)

  if (!total) return <p className="muted">No hit data for this kill (e.g. knife or bomb).</p>

  return (
    <div className="bodymap-wrap">
      <div className="bodymap">
        <div className="bodymap-silhouette" />
        {ZONES.map((z) => {
          const dmg = hg[z.key]
          if (!dmg) return null
          const alpha = 0.4 + 0.55 * (dmg / max)
          return (
            <span
              key={z.key}
              className="bz"
              style={{ top: `${z.top}%`, left: `${z.left}%`, backgroundColor: `rgba(217, 60, 60, ${alpha})` }}
              title={`${z.label}: ${dmg} dmg`}
            >
              {dmg}
            </span>
          )
        })}
      </div>
      <p className="muted bodymap-total">{total} damage across {values.length} zone{values.length === 1 ? '' : 's'}</p>
    </div>
  )
}
