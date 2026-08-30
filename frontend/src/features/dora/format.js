// Presentation for the metric values. The backend sends numbers and a bucket; naming and
// formatting are the frontend's job, same as error codes.

export const BUCKETS = ['elite', 'high', 'medium', 'low']

// A duration in seconds, rendered at the coarsest unit that still says something useful:
// a 5-day lead time reads worse as "120h" than as "5d".
export function humanDuration(seconds) {
  if (seconds === null || seconds === undefined) return '—'
  if (seconds < 60) return `${Math.round(seconds)}s`
  if (seconds < 3600) return `${Math.round(seconds / 60)}m`
  if (seconds < 86400) return `${(seconds / 3600).toFixed(1)}h`
  return `${(seconds / 86400).toFixed(1)}d`
}

export function percent(ratio, digits = 1) {
  if (ratio === null || ratio === undefined) return '—'
  return `${(ratio * 100).toFixed(digits)}%`
}

export function perDay(value) {
  if (value === null || value === undefined) return '—'
  if (value >= 1) return `${value.toFixed(1)}/day`
  return `${(value * 7).toFixed(1)}/week`
}
