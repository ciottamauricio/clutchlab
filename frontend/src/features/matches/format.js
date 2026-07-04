// Display helpers shared by the match card and the dashboard title.

// Date + time (e.g. "Jul 3, 2026, 2:32 PM"), locale-aware. This is the upload timestamp —
// the demo's actual in-game date/time isn't parsed.
export function formatMatchDate(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleString(undefined, {
    year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit',
  })
}

// Compact duration (e.g. "1h 12m", "48m", "36s"). Minutes are dropped when there are hours
// and it's an even hour; seconds only show for sub-minute durations.
export function formatDuration(seconds) {
  if (seconds == null || seconds <= 0) return ''
  const total = Math.round(seconds)
  const h = Math.floor(total / 3600)
  const m = Math.floor((total % 3600) / 60)
  const s = total % 60
  if (h > 0) return m > 0 ? `${h}h ${m}m` : `${h}h`
  if (m > 0) return `${m}m`
  return `${s}s`
}

// The two teams as { name, score, side }, higher score first (winner on the left).
// Names fall back to side labels for pug demos that carry no clan name.
export function matchTeams(match) {
  const ct = { name: match.ct_name || 'Counter-Terrorists', score: match.score_ct ?? 0, side: 'CT' }
  const t = { name: match.t_name || 'Terrorists', score: match.score_t ?? 0, side: 'T' }
  return [t, ct].sort((a, b) => b.score - a.score)
}
