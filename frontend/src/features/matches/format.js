// Display helpers shared by the match card and the dashboard title.

export function formatMatchDate(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
}

// The two teams as { name, score, side }, higher score first (winner on the left).
// Names fall back to side labels for pug demos that carry no clan name.
export function matchTeams(match) {
  const ct = { name: match.ct_name || 'Counter-Terrorists', score: match.score_ct ?? 0, side: 'CT' }
  const t = { name: match.t_name || 'Terrorists', score: match.score_t ?? 0, side: 'T' }
  return [t, ct].sort((a, b) => b.score - a.score)
}
