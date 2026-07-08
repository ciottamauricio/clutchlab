import { useCallback, useState } from 'react'

// Dark/light theme, driven by a `data-theme` attribute on <html> and persisted to
// localStorage. The initial attribute is set by an inline script in index.html (before
// paint) to avoid a flash of the wrong theme; this hook only reads and flips it.
const KEY = 'clutchlab_theme'

const currentTheme = () => (document.documentElement.dataset.theme === 'light' ? 'light' : 'dark')

export function useTheme() {
  const [theme, setTheme] = useState(currentTheme)

  const set = useCallback((t) => {
    document.documentElement.dataset.theme = t
    try {
      localStorage.setItem(KEY, t)
    } catch {
      // storage may be unavailable (private mode) — theme still applies for this session
    }
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content', t === 'light' ? '#f3f5f8' : '#0e1116')
    setTheme(t)
  }, [])

  const toggle = useCallback(() => set(currentTheme() === 'light' ? 'dark' : 'light'), [set])

  return { theme, toggle }
}
