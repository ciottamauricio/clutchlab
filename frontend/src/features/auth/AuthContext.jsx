import { createContext, useContext, useEffect, useState } from 'react'
import { api, tokenStore } from '../../lib/api'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [loading, setLoading] = useState(true)

  // On boot, if we have a token, hydrate the current user from /me.
  useEffect(() => {
    let active = true
    if (!tokenStore.get()) {
      setLoading(false)
      return
    }
    api
      .get('/me')
      .then((u) => active && setUser(u))
      .catch(() => tokenStore.clear())
      .finally(() => active && setLoading(false))
    return () => {
      active = false
    }
  }, [])

  // api.js fires this when any request 401s (expired/removed token).
  useEffect(() => {
    const onUnauth = () => setUser(null)
    window.addEventListener('auth:unauthorized', onUnauth)
    return () => window.removeEventListener('auth:unauthorized', onUnauth)
  }, [])

  const login = async (email, password) => {
    const { user, token } = await api.post('/login', { email, password }, { auth: false })
    tokenStore.set(token)
    setUser(user)
  }

  const register = async (data) => {
    const { user, token } = await api.post('/register', data, { auth: false })
    tokenStore.set(token)
    setUser(user)
  }

  // Re-hydrate the current user from /me — call after a profile edit so the shell (navbar
  // name) and any consumer reflect the change.
  const refreshUser = async () => {
    const u = await api.get('/me')
    setUser(u)
    return u
  }

  const logout = async () => {
    try {
      await api.post('/logout')
    } catch {
      // ignore — we clear locally regardless
    }
    tokenStore.clear()
    setUser(null)
  }

  return (
    <AuthContext.Provider value={{ user, loading, login, register, logout, refreshUser }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  return useContext(AuthContext)
}

// App-scope ability check for the current user — drives whether whole pages (Awards, Search,
// Tactics) show. Admins receive the full app-ability set from /me, so this needs no special
// case. Team-scope abilities are contextual and enforced by the server, not gated here.
export function useCan(ability) {
  const { user } = useContext(AuthContext)
  return Boolean(user?.abilities?.includes(ability))
}
