import { useRef, useState } from 'react'
import { useTacticBoard } from './api'
import { radarUrl } from '../matches/radar'

// Piece kinds. Colours live in CSS (.piece.k-<kind>), on the app's side tokens.
// The board JSON shape { id, kind, x, y, label } is the realtime contract — unchanged.
const PLAYERS = [
  { kind: 'ct', label: 'CT' },
  { kind: 't', label: 'T' },
]
const UTILITY = [
  { kind: 'smoke', label: 'Smoke' },
  { kind: 'flash', label: 'Flash' },
  { kind: 'he', label: 'HE' },
  { kind: 'molly', label: 'Molly' },
]
const SHORT = { ct: 'CT', t: 'T', smoke: 'S', flash: 'F', he: 'HE', molly: 'M' }

// crypto.randomUUID only exists on secure origins (https / localhost) — opening the app
// via a LAN or WSL IP would make every add-piece click throw. Ids just need uniqueness
// within one board, so a timestamp+random fallback is plenty.
const newId = () =>
  typeof crypto !== 'undefined' && crypto.randomUUID
    ? crypto.randomUUID()
    : `p-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`

export default function Board({ tacticId, map }) {
  const { board, presence, connected, update } = useTacticBoard(tacticId)
  const fieldRef = useRef(null)
  const dragId = useRef(null)
  const dragMoved = useRef(false)
  const [selectedId, setSelectedId] = useState(null)

  const pieces = board?.pieces ?? []
  const selected = pieces.find((p) => p.id === selectedId)

  // Player pieces number themselves (CT 1–5, T 1–5); utility starts unlabeled.
  const addPiece = (kind) => {
    const isPlayer = kind === 'ct' || kind === 't'
    const label = isPlayer ? String(pieces.filter((p) => p.kind === kind).length + 1) : ''
    update({ pieces: [...pieces, { id: newId(), kind, x: 0.5, y: 0.5, label }] }, true)
  }

  const removePiece = (id) => {
    if (selectedId === id) setSelectedId(null)
    update({ pieces: pieces.filter((p) => p.id !== id) }, true)
  }

  const setLabel = (id, label, force = false) =>
    update({ pieces: pieces.map((p) => (p.id === id ? { ...p, label } : p)) }, force)

  const clearBoard = () => {
    setSelectedId(null)
    update({ pieces: [] }, true)
  }

  const moveTo = (clientX, clientY) => {
    const r = fieldRef.current.getBoundingClientRect()
    const x = Math.min(1, Math.max(0, (clientX - r.left) / r.width))
    const y = Math.min(1, Math.max(0, (clientY - r.top) / r.height))
    update({ pieces: pieces.map((p) => (p.id === dragId.current ? { ...p, x, y } : p)) })
  }

  const onPointerMove = (e) => {
    if (dragId.current) {
      dragMoved.current = true
      moveTo(e.clientX, e.clientY)
    }
  }

  // A press that never moved is a select; a drag persists its final position.
  const endDrag = () => {
    if (!dragId.current) return
    if (dragMoved.current) {
      update(board, true)
    } else {
      setSelectedId((cur) => (cur === dragId.current ? null : dragId.current))
    }
    dragId.current = null
    dragMoved.current = false
  }

  return (
    <section className="board-wrap">
      <div className="board-toolbar">
        <span className="board-group">
          {PLAYERS.map((k) => (
            <button key={k.kind} type="button" className={`add-piece k-${k.kind}`} onClick={() => addPiece(k.kind)}>
              + {k.label}
            </button>
          ))}
        </span>
        <span className="board-sep" aria-hidden="true" />
        <span className="board-group">
          {UTILITY.map((k) => (
            <button key={k.kind} type="button" className={`add-piece k-${k.kind}`} onClick={() => addPiece(k.kind)}>
              + {k.label}
            </button>
          ))}
        </span>
        {pieces.length > 0 && (
          <button type="button" className="link-btn board-clear" onClick={clearBoard}>clear board</button>
        )}
        <span className={`presence${connected ? ' live' : ''}`}>
          <span className="presence-dot" aria-hidden="true" />
          {connected ? `${presence} online` : 'connecting…'}
        </span>
      </div>

      <div
        className={map ? 'board-field has-radar' : 'board-field'}
        style={map ? { backgroundImage: `url(${radarUrl(map)})` } : undefined}
        ref={fieldRef}
        onPointerMove={onPointerMove}
        onPointerUp={endDrag}
        onPointerLeave={endDrag}
      >
        {pieces.map((p) => (
          <div
            key={p.id}
            className={`piece k-${p.kind}${p.id === selectedId ? ' selected' : ''}`}
            style={{ left: `${p.x * 100}%`, top: `${p.y * 100}%` }}
            title="drag to move · click to name · double-click to remove"
            onPointerDown={(e) => {
              e.preventDefault()
              dragId.current = p.id
              dragMoved.current = false
            }}
            onDoubleClick={() => removePiece(p.id)}
          >
            {(p.kind === 'ct' || p.kind === 't') && p.label ? p.label : SHORT[p.kind] ?? '?'}
            {p.label && p.kind !== 'ct' && p.kind !== 't' && (
              <span className="piece-tag">{p.label}</span>
            )}
          </div>
        ))}
      </div>

      {selected ? (
        <div className="board-inspector">
          <span className={`piece piece-chip k-${selected.kind}`}>{SHORT[selected.kind]}</span>
          <input
            value={selected.label ?? ''}
            placeholder={selected.kind === 'ct' || selected.kind === 't' ? 'number / name' : 'note (e.g. "jump throw")'}
            maxLength={24}
            onChange={(e) => setLabel(selected.id, e.target.value)}
            onBlur={() => update(board, true)}
            onKeyDown={(e) => e.key === 'Enter' && e.currentTarget.blur()}
          />
          <button type="button" className="link-btn" onClick={() => removePiece(selected.id)}>remove</button>
          <button type="button" className="link-btn" onClick={() => setSelectedId(null)}>done</button>
        </div>
      ) : (
        <p className="muted">Drag to move · click to name · double-click to remove · edits sync live to everyone on this tactic.</p>
      )}
    </section>
  )
}
