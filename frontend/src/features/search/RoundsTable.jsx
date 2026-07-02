export default function RoundsTable({ hits }) {
  if (!hits.length) return null

  return (
    <table className="members">
      <thead>
        <tr>
          <th>Round</th>
          <th>Winner</th>
          <th>Reason</th>
          <th>CT alive</th>
          <th>T alive</th>
          <th>CT buy</th>
          <th>T buy</th>
        </tr>
      </thead>
      <tbody>
        {hits.map((r) => (
          <tr key={r.id}>
            <td>{r.round}</td>
            <td>{r.winner}</td>
            <td>{r.reason}</td>
            <td>{r.ct_alive}</td>
            <td>{r.t_alive}</td>
            <td>{r.ct_buy}</td>
            <td>{r.t_buy}</td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}
