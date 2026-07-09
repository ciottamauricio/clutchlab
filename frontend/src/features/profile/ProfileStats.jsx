const TILES = [
  { key: 'kd', label: 'K/D' },
  { key: 'hs_pct', label: 'HS%', suffix: '%' },
  { key: 'games', label: 'Games' },
  { key: 'kills', label: 'Kills' },
  { key: 'deaths', label: 'Deaths' },
  { key: 'entry_kills', label: 'Entry' },
  { key: 'first_deaths', label: '1st deaths' },
  { key: 'clutches', label: 'Clutches' },
]

// The player card's stat strip — the page's hero readout. `stats` is null when the account
// has no SteamID linked yet (linking is admin-only — see the admin panel).
export default function ProfileStats({ stats }) {
  if (!stats) {
    return (
      <p className="pc-stat-empty">
        No SteamID linked to your account yet. Ask an admin to link you to a player to see your stats here.
      </p>
    )
  }

  return (
    <div className="pc-stat-strip">
      {TILES.map(({ key, label, suffix }) => (
        <div className="pc-stat" key={key}>
          <span className="pc-stat-v">{stats[key]}{suffix ?? ''}</span>
          <span className="pc-stat-l">{label}</span>
        </div>
      ))}
    </div>
  )
}
