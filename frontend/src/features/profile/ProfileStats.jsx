// The player card's stat strip — the page's hero readout, ranked rather than flat:
// K/D leads with its side of the 1.0 break-even (the same reference the recent-form bars
// use), the two duels (kills–deaths, entry kills–first deaths) read as paired records,
// and the context counts trail. `stats` is null when the account has no SteamID linked
// yet (linking is admin-only — see the admin panel).
export default function ProfileStats({ stats }) {
  if (!stats) {
    return (
      <p className="pc-stat-empty">
        No SteamID linked to your account yet. Ask an admin to link you to a player to see your stats here.
      </p>
    )
  }

  const above = stats.kd >= 1
  const tiles = [
    { label: 'K–D', value: `${stats.kills}–${stats.deaths}`, pair: true, title: 'Total kills–deaths' },
    { label: 'Opening duels', value: `${stats.entry_kills}–${stats.first_deaths}`, pair: true, title: 'Entry kills–first deaths' },
    { label: 'HS%', value: `${stats.hs_pct}%` },
    { label: 'Clutches', value: stats.clutches },
    { label: 'Games', value: stats.games },
  ]

  return (
    <div className="pc-stat-strip">
      <div className="pc-stat pc-stat-lead">
        <span className="pc-stat-v">{Number(stats.kd).toFixed(2)}</span>
        <span className={`pc-kd-tag ${above ? 'up' : 'down'}`} title="Break-even is 1.0">
          {above ? '▲ above even' : '▼ below even'}
        </span>
        <span className="pc-stat-l">K/D</span>
      </div>

      {tiles.map(({ label, value, pair, title }) => (
        <div className="pc-stat" key={label} title={title}>
          <span className={`pc-stat-v${pair ? ' pc-pair' : ''}`}>{value}</span>
          <span className="pc-stat-l">{label}</span>
        </div>
      ))}
    </div>
  )
}
