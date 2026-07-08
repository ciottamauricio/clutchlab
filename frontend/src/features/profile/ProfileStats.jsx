const TILES = [
  { key: 'games', label: 'Games' },
  { key: 'kills', label: 'Kills' },
  { key: 'deaths', label: 'Deaths' },
  { key: 'kd', label: 'K/D' },
  { key: 'hs_pct', label: 'HS%', suffix: '%' },
  { key: 'entry_kills', label: 'Entry' },
  { key: 'first_deaths', label: '1st deaths' },
  { key: 'clutches', label: 'Clutches' },
]

// The linked player's aggregate line across the viewer's matches. `stats` is null when the
// account has no SteamID linked yet (linking is admin-only — see the admin panel).
export default function ProfileStats({ stats }) {
  if (!stats) {
    return (
      <div className="pf-card pf-stats-empty">
        <h3>Player stats</h3>
        <p className="muted">No SteamID linked to your account yet. Ask an admin to link you to a player to see your stats here.</p>
      </div>
    )
  }

  return (
    <div className="pf-card">
      <h3>Player stats</h3>
      <div className="pf-stats">
        {TILES.map(({ key, label, suffix }) => (
          <div className="pf-stat" key={key}>
            <span className="pf-stat-v">{stats[key]}{suffix ?? ''}</span>
            <span className="pf-stat-l">{label}</span>
          </div>
        ))}
      </div>
    </div>
  )
}
