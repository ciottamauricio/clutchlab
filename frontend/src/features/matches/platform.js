// Where a match was played (mirrors GameMatch::PLATFORMS); labels come from t('platform.<code>').
export const PLATFORMS = ['steam', 'faceit', 'gamersclub', 'other']

// Each platform names its downloads its own way, so the filename is a decent first guess.
// Only a guess: it pre-selects the upload picker and the uploader confirms or changes it.
const FILENAME_PATTERNS = [
  ['gamersclub', /^\d{4}-\d{2}-\d{2}__\d{4}__/],
  ['steam', /^match730_/],
  ['faceit', /^1-[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}/i],
]

export function guessPlatform(filename) {
  const hit = FILENAME_PATTERNS.find(([, pattern]) => pattern.test(filename ?? ''))
  return hit ? hit[0] : ''
}
