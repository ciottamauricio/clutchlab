// Renders a single team's roster. The dashboard passes one of these per side.
export default function Scoreboard({ title, side, score, players }) {
  if (!players?.length) return null

  return (
    <div className="team">
      <header className="team-head">
        <span className={`side side-${side}`}>{side || '—'}</span>
        <span className="team-name">{title}</span>
        {score != null && <span className="team-score">{score}</span>}
      </header>
      <table className="scoreboard">
        <thead>
          <tr>
            <th>Player</th>
            <th>K</th>
            <th>D</th>
            <th>A</th>
            <th>HS</th>
          </tr>
        </thead>
        <tbody>
          {players.map((p) => (
            <tr key={p.steam_id}>
              <td>{p.name}</td>
              <td>{p.kills}</td>
              <td>{p.deaths}</td>
              <td>{p.assists}</td>
              <td>{p.headshots}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
