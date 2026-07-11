import { useRef } from 'react'
import { useTacticBoard } from './api'
import { radarUrl } from '../matches/radar'

// Piece kinds and their colours. `kind` is stored on each piece; the label/colour
// are presentational only.
const KINDS = [
  { kind: 'ct', label: 'CT', color: '#4a90d9' },
  { kind: 't', label: 'T', color: '#d9a520' },
  { kind: 'smoke', label: 'S', color: '#8b8b8b' },
  { kind: 'flash', label: 'F', color: '#d8d84a' },
  { kind: 'he', label: 'HE', color: '#4caf50' },
  { kind: 'molly', label: 'M', color: '#e0592a' },
]

const colorOf = (kind) => (KINDS.find((k) => k.kind === kind) ?? KINDS[0]).color
const labelOf = (kind) => (KINDS.find((k) => k.kind === kind) ?? KINDS[0]).label

export default function Board({ tacticId, map }) {
  const { board, presence, connected, update } = useTacticBoard(tacticId)
  const fieldRef = useRef(null)
  const dragId = useRef(null)

  const pieces = board?.pieces ?? []

  const addPiece = (kind) =>
    update({ pieces: [...pieces, { id: crypto.randomUUID(), kind, x: 0.5, y: 0.5, label: '' }] }, true)

  const removePiece = (id) => update({ pieces: pieces.filter((p) => p.id !== id) }, true)

  const moveTo = (clientX, clientY) => {
    const r = fieldRef.current.getBoundingClientRect()
    const x = Math.min(1, Math.max(0, (clientX - r.left) / r.width))
    const y = Math.min(1, Math.max(0, (clientY - r.top) / r.height))
    update({ pieces: pieces.map((p) => (p.id === dragId.current ? { ...p, x, y } : p)) })
  }

  const onPointerMove = (e) => {
    if (dragId.current) moveTo(e.clientX, e.clientY)
  }

  const endDrag = () => {
    if (dragId.current) {
      update(board, true) // force the final position to persist + broadcast
      dragId.current = null
    }
  }

  return (
    <section className="board-wrap">
      <div className="board-toolbar">
        {KINDS.map((k) => (
          <button
            key={k.kind}
            type="button"
            className="add-piece"
            style={{ borderColor: k.color, color: k.color }}
            onClick={() => addPiece(k.kind)}
          >
            + {k.label}
          </button>
        ))}
        <span className="presence">{connected ? `${presence} online` : 'connecting…'}</span>
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
            className="piece"
            style={{ left: `${p.x * 100}%`, top: `${p.y * 100}%`, background: colorOf(p.kind) }}
            title="drag to move · double-click to remove"
            onPointerDown={(e) => {
              e.preventDefault()
              dragId.current = p.id
            }}
            onDoubleClick={() => removePiece(p.id)}
          >
            {labelOf(p.kind)}
          </div>
        ))}
      </div>

      <p className="muted">Drag pieces to move · double-click to remove · edits sync live to everyone on this tactic.</p>
    </section>
  )
}
