const BASE = import.meta.env.VITE_API_URL ?? '/api'
const TOKEN_KEY = 'clutchlab_token'

export const tokenStore = {
  get: () => localStorage.getItem(TOKEN_KEY),
  set: (t) => localStorage.setItem(TOKEN_KEY, t),
  clear: () => localStorage.removeItem(TOKEN_KEY),
}

export class ApiError extends Error {
  constructor(code, status) {
    super(code)
    this.code = code
    this.status = status
  }
}

// The backend speaks error codes, never sentences (docs/ARCHITECTURE.md). Pull the
// first code out of Laravel's validation shape, or fall back to message/error.
function firstCode(body) {
  const errors = body?.errors
  if (errors) {
    const first = Object.values(errors)[0]
    if (Array.isArray(first) && first[0]) return first[0]
  }
  return body?.message ?? body?.error ?? 'error.unknown'
}

async function request(path, { auth = true, headers = {}, ...options } = {}) {
  const finalHeaders = { Accept: 'application/json', ...headers }
  const token = tokenStore.get()
  if (auth && token) finalHeaders.Authorization = `Bearer ${token}`

  const res = await fetch(`${BASE}${path}`, { ...options, headers: finalHeaders })
  const body = await res.json().catch(() => null)

  if (!res.ok) {
    if (res.status === 401) {
      // Token missing/expired — drop it and let the app fall back to login.
      tokenStore.clear()
      window.dispatchEvent(new Event('auth:unauthorized'))
    }
    throw new ApiError(firstCode(body), res.status)
  }

  return body?.data
}

export const api = {
  get: (path) => request(path),
  post: (path, data, opts) =>
    request(path, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data ?? {}),
      ...opts,
    }),
  postForm: (path, formData) => request(path, { method: 'POST', body: formData }),
  delete: (path) => request(path, { method: 'DELETE' }),
}
