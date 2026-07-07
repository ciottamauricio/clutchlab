import { useState } from 'react'
import { downloadDemo } from './api'

// How many seconds before the kill the demo jump lands, so you see the lead-up.
const LEAD_SECONDS = 5

// Clipboard that also works outside a secure context (navigator.clipboard is undefined on
// non-localhost HTTP), falling back to a hidden textarea + execCommand.
async function writeClipboard(text) {
  try {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(text)
      return true
    }
  } catch {
    // fall through to the legacy path
  }
  try {
    const ta = document.createElement('textarea')
    ta.value = text
    ta.style.position = 'fixed'
    ta.style.opacity = '0'
    document.body.appendChild(ta)
    ta.focus()
    ta.select()
    const ok = document.execCommand('copy')
    ta.remove()
    return ok
  } catch {
    return false
  }
}

const CopyIcon = () => (
  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
    <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
  </svg>
)

const CheckIcon = () => (
  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
    <polyline points="20 6 9 17 4 12" />
  </svg>
)

// A copy-paste "watch this kill in CS2" block. playdemo and demo_gototick are kept as two
// separate commands on purpose: playdemo loads asynchronously, so a chained gototick fires
// before playback starts and is ignored (you'd land at warmup). Run the jump once the demo
// is actually playing. The browser can't push commands into a running game, so this is
// copy-paste, not one-click.
export default function WatchInGame({ matchId, tick, tickRate, demo, killer }) {
  const [copied, setCopied] = useState(null) // 'load' | 'jump' | null
  const [downloading, setDownloading] = useState(false)

  if (tick == null) return null

  const goto = Math.max(0, Math.round(tick - LEAD_SECONDS * (tickRate || 64)))
  const name = (demo || '').replace(/\.dem$/i, '')
  const loadCmd = `playdemo "${name}"`
  const jumpCmd = `demo_gototick ${goto}; demo_pause`

  const copy = (which, text) =>
    writeClipboard(text).then((ok) => {
      if (!ok) return
      setCopied(which)
      setTimeout(() => setCopied(null), 1500)
    })

  const download = () => {
    setDownloading(true)
    downloadDemo(matchId, demo).finally(() => setDownloading(false))
  }

  const copyButton = (which, text) => (
    <button type="button" className="copy-btn" title="Copy" aria-label="Copy" onClick={() => copy(which, text)}>
      {copied === which ? <CheckIcon /> : <CopyIcon />}
    </button>
  )

  return (
    <div className="watch">
      {killer && <p className="watch-killer">Kill by <strong>{killer}</strong></p>}

      <div className="watch-cmd">
        <code>{loadCmd}</code>
        {copyButton('load', loadCmd)}
      </div>
      <div className="watch-cmd">
        <code>{jumpCmd}</code>
        {copyButton('jump', jumpCmd)}
      </div>

      <ol className="watch-steps">
        <li>
          <button type="button" className="link-btn" disabled={downloading} onClick={download}>
            {downloading ? 'Downloading…' : 'Download demo'}
          </button>{' '}into your CS2 folder{' '}
          <code className="watch-path">C:\Program Files (x86)\Steam\steamapps\common\Counter-Strike Global Offensive\game\csgo</code>{' '}
          — or in CS2 use <strong>Watch → Load a demo file</strong>
        </li>
        <li>Run the first command (or load it from the menu) and wait for the demo to start</li>
        <li>Run the second command — it lands paused ~{LEAD_SECONDS}s before the kill</li>
        {killer && <li>Scroll or click the spectator bar to follow <strong>{killer}</strong> (the killer)</li>}
      </ol>
    </div>
  )
}
