import { useState } from 'react'
import { useDoraMetrics } from '../features/dora/api'
import MetricCard from '../features/dora/MetricCard'
import TrendChart from '../features/dora/TrendChart'
import { humanDuration, percent, perDay } from '../features/dora/format'
import { t } from '../lib/i18n'

const WINDOWS = [7, 30, 90]

// Delivery metrics for the project itself: how often it ships, how long a change takes to
// get out, how often that goes wrong, how fast it recovers — plus the one metric measured
// from the product rather than the pipeline (do uploads actually parse, quickly).
export default function DoraPage() {
  const [windowDays, setWindowDays] = useState(30)
  const { data, loading, error } = useDoraMetrics(windowDays)

  const m = data?.metrics

  return (
    <section className="dora">
      <div className="dora-head">
        <h2>Delivery metrics</h2>
        <div className="dora-windows">
          {WINDOWS.map((w) => (
            <button
              key={w}
              type="button"
              className={w === windowDays ? 'active' : ''}
              onClick={() => setWindowDays(w)}
            >
              {w}d
            </button>
          ))}
        </div>
      </div>

      {error && <p className="error">{t(error)}</p>}
      {loading && !data && <p className="muted">Measuring…</p>}

      {m && (
        <>
          <div className="dora-grid">
            <MetricCard
              label="Deployment frequency"
              value={perDay(m.deployment_frequency.value)}
              bucket={m.deployment_frequency.bucket}
              sample={m.deployment_frequency.sample}
            />
            <MetricCard
              label="Lead time for changes"
              value={humanDuration(m.lead_time.value_seconds)}
              bucket={m.lead_time.bucket}
              note="commit authored → running in production"
              sample={m.lead_time.sample}
            />
            <MetricCard
              label="Change failure rate"
              value={percent(m.change_failure_rate.value)}
              bucket={m.change_failure_rate.bucket}
              sample={m.change_failure_rate.sample}
            />
            <MetricCard
              label="Time to restore"
              value={humanDuration(m.time_to_restore.value_seconds)}
              bucket={m.time_to_restore.bucket}
              note="incident opened → resolved"
              sample={m.time_to_restore.sample}
            />
            <MetricCard
              label="Reliability (SLO)"
              value={percent(m.reliability.value)}
              note={`target ${percent(m.reliability.target, 0)} of demos parsed under 3 min`}
              sample={m.reliability.sample}
            />
          </div>

          <h3 className="dora-subhead">Deploys vs. failures</h3>
          <TrendChart trend={data.trend} />

          <p className="dora-generated">
            Window: {data.window_days} days · generated {new Date(data.generated_at).toLocaleString()}
          </p>
        </>
      )}
    </section>
  )
}
