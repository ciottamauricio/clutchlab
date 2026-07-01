export default function Scoreboard({ players }) {
  if (!players?.length) return null

  return (
    <table className="scoreboard">
      <thead>
        <tr>
          <th>Player</th>
          <th>Side</th>
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
            <td>{p.team_side || '—'}</td>
            <td>{p.kills}</td>
            <td>{p.deaths}</td>
            <td>{p.assists}</td>
            <td>{p.headshots}</td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}
