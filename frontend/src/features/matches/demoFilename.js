// What a demo's filename suggests about the match. Each platform names its downloads its own
// way, so the name is a decent first guess — only a guess: it pre-fills the upload form and
// the uploader confirms or changes it.

const PLATFORM_PATTERNS = [
  ['gamersclub', /^\d{4}-\d{2}-\d{2}__\d{4}__/],
  ['steam', /^match730_/],
  ['faceit', /^1-[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}/i],
]

export function guessPlatform(filename) {
  const hit = PLATFORM_PATTERNS.find(([, pattern]) => pattern.test(filename ?? ''))
  return hit ? hit[0] : ''
}

// Gamers Club names start with when the match was played, in UTC (`YYYY-MM-DD__HHMM`; same
// reading as GameMatch::playedAtFromFilename). Returns an ISO string, or '' when absent.
export function guessPlayedAt(filename) {
  const m = /(\d{4})-(\d{2})-(\d{2})__(\d{2})(\d{2})/.exec(filename ?? '')
  return m ? `${m[1]}-${m[2]}-${m[3]}T${m[4]}:${m[5]}:00Z` : ''
}
