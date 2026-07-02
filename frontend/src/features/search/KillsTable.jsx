export default function KillsTable({ hits }) {
  if (!hits.length) return null

  return (
    <table className="members">
      <thead>
        <tr>
          <th>Round</th>
          <th>Killer</th>
          <th>Victim</th>
          <th>Weapon</th>
          <th>HS</th>
          <th>Opening</th>
          <th>Side</th>
        </tr>
      </thead>
      <tbody>
        {hits.map((k) => (
          <tr key={k.id}>
            <td>{k.round}</td>
            <td>{k.killer_name}</td>
            <td>{k.victim_name}</td>
            <td>{k.weapon}</td>
            <td>{k.headshot ? '✓' : ''}</td>
            <td>{k.opening ? '✓' : ''}</td>
            <td>{k.side}</td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}
