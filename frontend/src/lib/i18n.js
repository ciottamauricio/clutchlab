// The one place codes become words. Backend services (Laravel, Go) only ever return
// codes; this is where they turn into text. i18n is deferred (docs/ARCHITECTURE.md),
// so this is a placeholder: when the time comes it becomes react-i18next backed by
// locales/en.json + locales/pt-BR.json, and t() keeps the same call signature.
const MESSAGES = {
  queued: 'Queued',
  parsing: 'Parsing…',
  parsed: 'Parsed',
  failed: 'Failed',

  parse_failed_download: 'Could not download the demo from storage',
  parse_failed_corrupt: 'The demo could not be parsed (corrupt or unsupported)',
  parse_failed_internal: 'An internal error occurred while saving results',

  'demo.required': 'Please choose a .dem file',
  'demo.invalid': 'That upload is not a valid file',
  'demo.wrong_extension': 'Only .dem files are accepted',
  'demo.file_too_large': 'That demo is too large (max 500 MB)',
  'demo.storage_failed': 'Uploading to storage failed',

  'error.unknown': 'Something went wrong',
}

export function t(code) {
  return MESSAGES[code] ?? code
}
