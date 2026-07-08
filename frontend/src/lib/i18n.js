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
  'demo.file_too_large': 'That demo is too large (max 1 GB)',
  'demo.storage_failed': 'Uploading to storage failed',
  'match.invalid_team': "You can't upload to that team",

  'auth.name_required': 'Please enter your name',
  'auth.email_required': 'Please enter your email',
  'auth.email_invalid': 'That email looks invalid',
  'auth.email_taken': 'That email is already registered',
  'auth.password_required': 'Please enter a password',
  'auth.password_mismatch': "Passwords don't match",
  'auth.password_too_short': 'Password must be at least 8 characters',
  'auth.invalid_credentials': 'Wrong email or password',

  'team.name_required': 'Please name the team',
  'team.email_required': 'Please enter an email',
  'team.user_not_found': 'No user with that email',
  'team.role_required': 'Please pick a role',
  'team.role_invalid': 'That role is not allowed',
  'team.already_member': 'That user is already on the team',
  'team.steam_id_required': 'Pick a player to add',

  'user.invalid_role': 'That role is not allowed',
  'user.invalid_steam_id': 'A SteamID64 is 17 digits',
  'user.steam_id_taken': 'That SteamID is already linked to another user',
  'admin.last_admin': "You can't remove the last admin",
  'admin.cannot_delete_self': "You can't delete your own account",

  'profile.invalid_role': 'Please pick a valid role',
  'profile.bio_too_long': 'Bio is too long (max 500 characters)',

  'tactic.name_required': 'Please name the tactic',

  'search.unavailable': 'Search is temporarily unavailable',

  'error.unknown': 'Something went wrong',
}

export function t(code) {
  return MESSAGES[code] ?? code
}
