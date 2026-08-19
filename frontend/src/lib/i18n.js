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
  parse_failed_timeout: 'The demo took too long to parse and was stopped',
  parse_failed_memory: 'The demo used too much memory to parse and was stopped',
  parse_failed_internal: 'An internal error occurred while saving results',

  'demo.required': 'Please choose a .dem file',
  'demo.invalid': 'That upload is not a valid file',
  'demo.wrong_extension': 'Only .dem files are accepted',
  'demo.file_too_large': 'That demo is too large (max 1 GB)',
  'demo.storage_failed': 'Uploading to storage failed',
  'match.invalid_team': "You can't upload to that team",
  'match.upload_forbidden': "You don't have permission to upload matches",
  'match.duplicate': "You've already uploaded this demo",
  'match.invalid_player_filter': 'That player filter is invalid',
  'match.invalid_month': 'That month filter is invalid',
  'match.invalid_day': 'That day filter is invalid',
  'match.invalid_page': 'That page does not exist',
  'tactic.invalid_team': "You can only share with a team you belong to",

  'training.invalid_team': "You can't schedule trainings for that team",
  'training.invalid_title': 'Please give the session a title (max 120 chars)',
  'training.invalid_time': 'Please pick a valid date and time',
  'training.invalid_duration': 'Session length must be 1\u2013600 minutes',
  'training.invalid_notes': 'Notes are too long (max 2000 chars)',
  'training.invalid_player': 'Everyone on the roster must be a team member',
  'training.invalid_tactic': 'Tactics must be visible to that team',
  'training.invalid_canceled': 'Invalid cancel flag',
  'training.invalid_assignee': 'Homework can only be assigned to players on the roster',
  'training.invalid_nade': 'Pick a map and a valid grenade type',
  'training.invalid_done': 'Invalid studied flag',
  'training.invalid_rsvp': 'Invalid RSVP answer',

  'auth.name_required': 'Please enter your name',
  'auth.email_required': 'Please enter your email',
  'auth.email_invalid': 'That email looks invalid',
  'auth.email_taken': 'That email is already registered',
  'auth.password_required': 'Please enter a password',
  'auth.password_mismatch': "Passwords don't match",
  'auth.password_too_short': 'Password must be at least 8 characters',
  'auth.invalid_credentials': 'Wrong email or password',
  'auth.current_password_required': 'Please enter your current password',
  'auth.current_password_incorrect': 'That current password is wrong',

  'team.name_required': 'Please name the team',
  'team.email_required': 'Please enter an email',
  'team.user_not_found': 'No user with that email',
  'team.role_required': 'Please pick a role',
  'team.role_invalid': 'That role is not allowed',
  'team.already_member': 'That user is already on the team',
  'team.owner_only': 'Only an owner can add or remove an owner',
  'team.steam_id_required': 'Pick a player to add',

  'user.invalid_role': 'That role is not allowed',
  'user.invalid_steam_id': 'A SteamID64 is 17 digits',
  'user.steam_id_taken': 'That SteamID is already linked to another user',
  'admin.last_admin': "You can't remove the last admin",
  'admin.cannot_delete_self': "You can't delete your own account",
  'permission.invalid_scope': 'Unknown permission scope',
  'permission.invalid_role': 'Unknown role for that scope',
  'permission.unknown_ability': 'Unknown ability',

  'profile.invalid_role': 'Please pick a valid role',
  'profile.bio_too_long': 'Bio is too long (max 500 characters)',

  'tactic.name_required': 'Please name the tactic',

  'search.unavailable': 'Search is temporarily unavailable',

  'analyst.unavailable': 'The analyst is temporarily unavailable',
  'analyst.question_required': 'Type a question first',
  'analyst.question_too_short': 'Ask a fuller question (at least 5 characters)',
  'analyst.question_too_long': 'Keep the question under 500 characters',

  'docs.unavailable': 'The docs assistant is temporarily unavailable',
  'docs.not_indexed': 'The documentation index is empty — run docs:embed to build it',
  'docs.question_required': 'Type a question first',
  'docs.question_too_short': 'Ask a fuller question (at least 5 characters)',
  'docs.question_too_long': 'Keep the question under 500 characters',

  'error.unknown': 'Something went wrong',
}

export function t(code) {
  return MESSAGES[code] ?? code
}
