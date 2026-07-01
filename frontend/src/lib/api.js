const BASE = import.meta.env.VITE_API_URL ?? '/api'

export class ApiError extends Error {
  constructor(code, status) {
    super(code)
    this.code = code
    this.status = status
  }
}

async function request(path, options) {
  const res = await fetch(`${BASE}${path}`, options)
  const body = await res.json().catch(() => null)

  if (!res.ok) {
    // The backend speaks error codes, never sentences (docs/ARCHITECTURE.md).
    // Surface the code; the i18n layer turns it into words.
    const code = body?.errors?.demo?.[0] ?? body?.message ?? body?.error ?? 'error.unknown'
    throw new ApiError(code, res.status)
  }

  return body?.data
}

export function listMatches() {
  return request('/matches')
}

export function getMatch(id) {
  return request(`/matches/${id}`)
}

export function uploadDemo(file) {
  const form = new FormData()
  form.append('demo', file)
  return request('/matches', { method: 'POST', body: form })
}
