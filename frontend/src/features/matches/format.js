// Display helpers shared by the match card and the dashboard title.

// The date to show for a match: when it was actually played (parsed from the demo filename,
// `played_at`) when available, otherwise the upload time as a fallback.
export function matchDate(match) {
  return match?.played_at || match?.created_at || null
}

// Date + time as dd/mm/yyyy, HH:MM (e.g. "03/07/2026, 14:32). The date is fixed dd/mm/yyyy;
// the clock stays locale-aware, both rendered in the viewer's local timezone.
export function formatMatchDate(iso) {
  if (!iso) return ''
  const d = new Date(iso)
  const pad = (n) => String(n).padStart(2, '0')
  const date = `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()}`
  const time = d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
  return `${date}, ${time}`
}

// Time of day only (HH:MM), locale-aware, in the viewer's timezone. For tight labels where
// the date is redundant (e.g. a list of recent matches).
export function formatMatchTime(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
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

// A compact map name for tight labels: drop the "de_"/"cs_" prefix and title-case
// (e.g. "de_dust2" → "Dust2"). Falls back to the raw value for unknown formats.
export function mapLabel(name) {
  if (!name) return '—'
  const bare = name.replace(/^(de|cs|ar)_/, '')
  return bare.charAt(0).toUpperCase() + bare.slice(1)
}

// The two teams as { name, score, side }, higher score first (winner on the left).
// Names fall back to side labels for pug demos that carry no clan name.
export function matchTeams(match) {
  const ct = { name: match.ct_name || 'Counter-Terrorists', score: match.score_ct ?? 0, side: 'CT' }
  const t = { name: match.t_name || 'Terrorists', score: match.score_t ?? 0, side: 'T' }
  return [t, ct].sort((a, b) => b.score - a.score)
}
