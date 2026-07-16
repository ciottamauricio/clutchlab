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

// Month helpers for the archive's month filter. A month is a plain `YYYY-MM` string —
// the same value the API's `?month=` filter takes.

export const currentMonth = () => {
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
}

// "2026-07" → "JUL 2026", in the viewer's locale.
export const monthLabel = (ym) => {
  const [y, m] = ym.split('-').map(Number)
  return new Date(y, m - 1, 1)
    .toLocaleDateString(undefined, { month: 'short', year: 'numeric' })
    .toUpperCase()
}

// Step a `YYYY-MM` by ±n months; the Date constructor handles year rollover.
export const stepMonth = (ym, delta) => {
  const [y, m] = ym.split('-').map(Number)
  const d = new Date(y, m - 1 + delta, 1)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}

// Day helpers — a day is a plain `YYYY-MM-DD`, matching the API's `?day=` filter.

export const todayYmd = () => {
  const now = new Date()
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
}

// "2026-07-12" → "SAT, 12 JUL", in the viewer's locale.
export const dayLabel = (ymd) => {
  const [y, m, d] = ymd.split('-').map(Number)
  return new Date(y, m - 1, d)
    .toLocaleDateString(undefined, { weekday: 'short', day: '2-digit', month: 'short' })
    .toUpperCase()
}

// Step a `YYYY-MM-DD` by ±n days; the Date constructor handles month/year rollover.
export const stepDay = (ymd, delta) => {
  const [y, m, d] = ymd.split('-').map(Number)
  const dt = new Date(y, m - 1, d + delta)
  return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`
}

// The two teams as { name, score, side }, higher score first (winner on the left).
// Names fall back to side labels for pug demos that carry no clan name.
export function matchTeams(match) {
  const ct = { name: match.ct_name || 'Counter-Terrorists', score: match.score_ct ?? 0, side: 'CT' }
  const t = { name: match.t_name || 'Terrorists', score: match.score_t ?? 0, side: 'T' }
  return [t, ct].sort((a, b) => b.score - a.score)
}
