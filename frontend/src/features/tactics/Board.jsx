import { useEffect, useRef, useState } from 'react'
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

// Pen colors are token names, not hexes — CSS maps .ink-<name> to the theme vars, so
// strokes recolor with the theme. Lines saved before colors existed default to accent.
const INKS = ['accent', 'ct', 't', 'danger']

// crypto.randomUUID only exists on secure origins (https / localhost) — opening the app
// via a LAN or WSL IP would make every add-piece click throw. Ids just need uniqueness
// within one board, so a timestamp+random fallback is plenty.
const newId = () =>
  typeof crypto !== 'undefined' && crypto.randomUUID
    ? crypto.randomUUID()
    : `p-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`

// Spawn-zone centres per map, as 0..1 fractions of the radar image — eyeballed from the
// green spawn outlines the overview PNGs in public/radars/ draw themselves. Maps without
// an entry (or boards with no map) fall back to top edge = CT, bottom edge = T.
const SPAWNS = {
  de_dust2: { ct: [0.61, 0.2], t: [0.38, 0.9] },
  de_mirage: { ct: [0.88, 0.36], t: [0.31, 0.71] },
  de_inferno: { ct: [0.89, 0.35], t: [0.1, 0.67] },
  de_nuke: { ct: [0.82, 0.48], t: [0.2, 0.55] },
  de_ancient: { ct: [0.5, 0.15], t: [0.48, 0.86] },
  de_anubis: { ct: [0.42, 0.22], t: [0.47, 0.9] },
  de_overpass: { ct: [0.48, 0.19], t: [0.66, 0.92] },
  de_train: { ct: [0.89, 0.5], t: [0.1, 0.17] },
  de_vertigo: { ct: [0.56, 0.24], t: [0.4, 0.74] },
  de_cache: { ct: [0.9, 0.54], t: [0.08, 0.44] },
}
const DEFAULT_SPAWNS = { ct: [0.5, 0.12], t: [0.5, 0.88] }

const MAX_ZOOM = 4

// Five positions fanned around a spawn centre (an X: leader + four corners), kept on-board.
const clamp01 = (v) => Math.min(0.97, Math.max(0.03, v))
const spawnSlots = ([cx, cy]) =>
  [[0, 0], [-0.045, -0.035], [0.045, -0.035], [-0.045, 0.035], [0.045, 0.035]]
    .map(([dx, dy]) => [clamp01(cx + dx), clamp01(cy + dy)])

export default function Board({ tacticId, map, canEdit = true }) {
  const { board, presence, connected, update } = useTacticBoard(tacticId, canEdit)
  const fieldRef = useRef(null)
  const canvasRef = useRef(null)
  const dragId = useRef(null)
  const dragMoved = useRef(false)
  const drawId = useRef(null)
  const panFrom = useRef(null)
  const [selectedId, setSelectedId] = useState(null)
  const [tool, setTool] = useState('move') // move | draw | erase
  const [penColor, setPenColor] = useState('accent')
  // The zoomed view, local to this viewer (never synced): the inner canvas is
  // translated (x, y)px then scaled z, so board coordinates stay 0..1 untouched.
  const [view, setView] = useState({ z: 1, x: 0, y: 0 })

  const pieces = board?.pieces ?? []
  const lines = board?.lines ?? []
  const selected = pieces.find((p) => p.id === selectedId)

  // Player pieces number themselves (CT 1–5, T 1–5); utility starts unlabeled.
  const addPiece = (kind) => {
    const isPlayer = kind === 'ct' || kind === 't'
    const label = isPlayer ? String(pieces.filter((p) => p.kind === kind).length + 1) : ''
    update({ ...board, pieces: [...pieces, { id: newId(), kind, x: 0.5, y: 0.5, label }] }, true)
  }

  const removePiece = (id) => {
    if (selectedId === id) setSelectedId(null)
    update({ ...board, pieces: pieces.filter((p) => p.id !== id) }, true)
  }

  const removeLine = (id) => update({ ...board, lines: lines.filter((l) => l.id !== id) }, true)

  const setLabel = (id, label, force = false) =>
    update({ ...board, pieces: pieces.map((p) => (p.id === id ? { ...p, label } : p)) }, force)

  const clearBoard = () => {
    setSelectedId(null)
    update({ ...board, pieces: [], lines: [] }, true)
  }

  // Full 5v5 at spawn: existing players keep their id/label and snap to a spawn slot,
  // missing ones are created (numbered on), extras beyond five stay where they are.
  const placeAtSpawns = () => {
    const spawns = SPAWNS[map] ?? DEFAULT_SPAWNS
    const next = [...pieces]
    for (const kind of ['ct', 't']) {
      const slots = spawnSlots(spawns[kind])
      const own = next.filter((p) => p.kind === kind)
      own.slice(0, 5).forEach((p, i) => {
        const at = next.indexOf(p)
        next[at] = { ...p, x: slots[i][0], y: slots[i][1] }
      })
      for (let i = own.length; i < 5; i++) {
        next.push({ id: newId(), kind, x: slots[i][0], y: slots[i][1], label: String(i + 1) })
      }
    }
    update({ ...board, pieces: next }, true)
  }

  // Board position from a pointer event, as 0..1 of the canvas. Measuring the canvas
  // (not the field) makes this zoom-proof: its rect already carries the transform.
  const fieldPos = (clientX, clientY) => {
    const r = canvasRef.current.getBoundingClientRect()
    return [
      Math.min(1, Math.max(0, (clientX - r.left) / r.width)),
      Math.min(1, Math.max(0, (clientY - r.top) / r.height)),
    ]
  }

  // Keep the canvas covering the field: at scale z it overflows by size·(z−1),
  // so the translation stays in [size·(1−z), 0].
  const clampPan = (val, size, z) => Math.min(0, Math.max(size * (1 - z), val))

  // Zoom about a field-relative point (px, py): the board spot under the pointer
  // stays under the pointer.
  const zoomAt = (factor, px, py) => {
    const r = fieldRef.current.getBoundingClientRect()
    setView((v) => {
      const z = Math.min(MAX_ZOOM, Math.max(1, v.z * factor))
      return {
        z,
        x: clampPan(px - ((px - v.x) * z) / v.z, r.width, z),
        y: clampPan(py - ((py - v.y) * z) / v.z, r.height, z),
      }
    })
  }

  const zoomStep = (factor) => {
    const r = fieldRef.current.getBoundingClientRect()
    zoomAt(factor, r.width / 2, r.height / 2)
  }

  const resetView = () => setView({ z: 1, x: 0, y: 0 })

  // Wheel zoom must preventDefault so the page doesn't scroll, and React registers
  // its synthetic wheel handler as passive — hence a native non-passive listener.
  useEffect(() => {
    const el = fieldRef.current
    if (!el) return
    const onWheel = (e) => {
      e.preventDefault()
      const r = el.getBoundingClientRect()
      zoomAt(Math.exp(-e.deltaY * 0.0015), e.clientX - r.left, e.clientY - r.top)
    }
    el.addEventListener('wheel', onWheel, { passive: false })
    return () => el.removeEventListener('wheel', onWheel)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const moveTo = (clientX, clientY) => {
    const [x, y] = fieldPos(clientX, clientY)
    update({ ...board, pieces: pieces.map((p) => (p.id === dragId.current ? { ...p, x, y } : p)) })
  }

  // Freehand pen: a pointer-down on the field starts a line, moves append points
  // (skipping sub-pixel jitter), pointer-up commits it — peers watch it grow live.
  const startLine = (e) => {
    if (tool !== 'draw' || e.button !== 0) return
    e.preventDefault()
    const id = newId()
    drawId.current = id
    update({ ...board, lines: [...lines, { id, color: penColor, points: [fieldPos(e.clientX, e.clientY)] }] })
  }

  // Pointer-down on the field: the pen starts a line; otherwise, when zoomed in with
  // the move tool, dragging the background pans the view. A press on a piece never
  // pans — the piece's own handler ran first (target before ancestors) and set dragId.
  const onFieldDown = (e) => {
    if (tool === 'draw') return startLine(e)
    if (tool === 'move' && !dragId.current && view.z > 1 && e.button === 0) {
      e.preventDefault()
      panFrom.current = { px: e.clientX, py: e.clientY, x: view.x, y: view.y }
    }
  }

  const extendLine = (clientX, clientY) => {
    const [x, y] = fieldPos(clientX, clientY)
    update({
      ...board,
      lines: lines.map((l) => {
        if (l.id !== drawId.current) return l
        const [lx, ly] = l.points[l.points.length - 1]
        if (Math.hypot(x - lx, y - ly) < 0.006) return l
        return { ...l, points: [...l.points, [x, y]] }
      }),
    })
  }

  const onPointerMove = (e) => {
    if (dragId.current) {
      dragMoved.current = true
      moveTo(e.clientX, e.clientY)
    } else if (drawId.current) {
      extendLine(e.clientX, e.clientY)
    } else if (panFrom.current) {
      const r = fieldRef.current.getBoundingClientRect()
      const { px, py, x, y } = panFrom.current
      setView((v) => ({
        ...v,
        x: clampPan(x + e.clientX - px, r.width, v.z),
        y: clampPan(y + e.clientY - py, r.height, v.z),
      }))
    }
  }

  // A press that never moved is a select; a drag persists its final position.
  const endDrag = () => {
    panFrom.current = null
    if (drawId.current) {
      // A stray click without movement leaves a 1-point line — drop it.
      const done = lines.find((l) => l.id === drawId.current)
      drawId.current = null
      if (done && done.points.length < 2) update({ ...board, lines: lines.filter((l) => l.id !== done.id) }, true)
      else update(board, true)
      return
    }
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
      <div className="board-rail">
      {!canEdit && (
        <p className="board-readonly">View only — you can watch edits live, but this tactic isn&apos;t yours to change.</p>
      )}
      {canEdit && (
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
        <span className="board-sep" aria-hidden="true" />
        <button
          type="button"
          className="add-piece board-spawns"
          title="Place all ten players at their spawns"
          onClick={placeAtSpawns}
        >
          5v5 spawns
        </button>
        <button
          type="button"
          className={`add-piece board-draw${tool === 'draw' ? ' on' : ''}`}
          title="Draw freehand lines — drag on the board"
          aria-pressed={tool === 'draw'}
          onClick={() => setTool((t) => (t === 'draw' ? 'move' : 'draw'))}
        >
          ✏ draw
        </button>
        {tool === 'draw' && (
          <span className="board-group board-inks" role="radiogroup" aria-label="Pen color">
            {INKS.map((c) => (
              <button
                key={c}
                type="button"
                className={`ink-swatch ink-${c}${penColor === c ? ' on' : ''}`}
                role="radio"
                aria-checked={penColor === c}
                aria-label={`Pen color ${c}`}
                onClick={() => setPenColor(c)}
              />
            ))}
          </span>
        )}
        <button
          type="button"
          className={`add-piece board-erase${tool === 'erase' ? ' on' : ''}`}
          title="Erase lines — click or sweep over them"
          aria-pressed={tool === 'erase'}
          onClick={() => setTool((t) => (t === 'erase' ? 'move' : 'erase'))}
        >
          ⌫ erase
        </button>
        {(pieces.length > 0 || lines.length > 0) && (
          <button type="button" className="link-btn board-clear" onClick={clearBoard}>clear board</button>
        )}
      </div>
      )}

        <span className={`presence${connected ? ' live' : ''}`}>
          <span className="presence-dot" aria-hidden="true" />
          {connected ? `${presence} online` : 'connecting…'}
        </span>
      </div>

      <div
        className={`board-field${map ? ' has-radar' : ''}${tool !== 'move' ? ` ${tool === 'draw' ? 'drawing' : 'erasing'}` : view.z > 1 ? ' pannable' : ''}`}
        ref={fieldRef}
        onPointerDown={onFieldDown}
        onPointerMove={onPointerMove}
        onPointerUp={endDrag}
        onPointerLeave={endDrag}
      >
        <div
          className="board-canvas"
          ref={canvasRef}
          style={{
            transform: `translate(${view.x}px, ${view.y}px) scale(${view.z})`,
            '--bz': view.z,
            ...(map ? { backgroundImage: `url(${radarUrl(map)})` } : {}),
          }}
        >
          <svg className="board-ink" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
            {lines.map((l) => (
              <polyline
                key={l.id}
                className={`ink-${l.color ?? 'accent'}`}
                points={l.points.map(([x, y]) => `${x * 100},${y * 100}`).join(' ')}
                onDoubleClick={() => removeLine(l.id)}
                onPointerDown={() => tool === 'erase' && removeLine(l.id)}
                onPointerOver={(e) => tool === 'erase' && e.buttons > 0 && removeLine(l.id)}
              >
                <title>{tool === 'erase' ? 'click to erase' : 'double-click to erase'}</title>
              </polyline>
            ))}
          </svg>
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
        <div className="board-zoom" onPointerDown={(e) => e.stopPropagation()}>
          <button type="button" aria-label="Zoom out" title="Zoom out" disabled={view.z <= 1} onClick={() => zoomStep(1 / 1.4)}>−</button>
          <span className="zoom-level">{view.z.toFixed(1)}×</span>
          <button type="button" aria-label="Zoom in" title="Zoom in" disabled={view.z >= MAX_ZOOM} onClick={() => zoomStep(1.4)}>+</button>
          {view.z > 1 && (
            <button type="button" aria-label="Reset zoom" title="Reset zoom" onClick={resetView}>⟲</button>
          )}
        </div>
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
        <p className="muted">Drag to move · click to name · double-click to remove · pen draws in the picked color · eraser clicks or sweeps lines away · scroll to zoom, drag the map to pan · edits sync live to everyone on this tactic.</p>
      )}
    </section>
  )
}
