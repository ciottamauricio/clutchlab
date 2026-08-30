// One metric: its value, what it counts, and the performance band it falls in.
//
// `sample` is shown rather than hidden because these are small numbers over a small
// window — a lead time drawn from 2 deploys and one drawn from 200 look identical
// otherwise, and the first is noise wearing the second's clothes.
export default function MetricCard({ label, value, bucket, note, sample }) {
  const measured = value !== '—'

  return (
    <article className={`dora-card${measured ? '' : ' dora-card-empty'}`}>
      <h3>{label}</h3>
      <p className="dora-value">{value}</p>

      {bucket && <span className={`dora-badge dora-badge-${bucket}`}>{bucket}</span>}

      {note && <p className="dora-note">{note}</p>}

      {measured
        ? sample !== undefined && <p className="dora-sample">from {sample} recorded</p>
        : <p className="dora-sample">not measured yet</p>}
    </article>
  )
}
