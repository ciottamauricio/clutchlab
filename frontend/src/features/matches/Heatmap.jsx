import { useEffect, useMemo, useRef, useState } from 'react'
import { useKillPositions } from './api'
import { hasRadar, radarUrl, toRadar } from './radar'
import WeaponSelect from '../search/WeaponSelect'
import BodyHitgroups from '../../components/BodyHitgroups'
import WatchInGame from './WatchInGame'
import { t } from '../../lib/i18n'

const SIDES = ['', 'CT', 'T']

// Kill heatmap: victim death locations plotted on the match's radar overview. Positions
// are fetched once and filtered here (api/docs/domains/heatmap.md).
export default function Heatmap({ matchId, players, teams }) {
  const { map, points, tickRate, demo, loading, error } = useKillPositions(matchId)
  const [f, setF] = useState({ killer_name: '', weapon: '', team: '', side: '', headshot: false })
  // Index (into `shown`) of the dot/row the mouse is over — links the two views.
  const [hovered, setHovered] = useState(null)
  // The kill whose body hitgroup map is open in the dialog (null = closed).
  const [selected, setSelected] = useState(null)

  useEffect(() => {
    if (!selected) return
    const onKey = (e) => e.key === 'Escape' && setSelected(null)
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [selected])

  // Radar zoom/pan. The image and dots share one transformed stage, so they stay aligned at
  // any zoom. `view` is { scale, x, y } (translate in px); pan is clamped so the map can't be
  // dragged off the viewport, and zoom keeps the point under the cursor fixed.
  const MIN_SCALE = 1
  const MAX_SCALE = 6
  const viewportRef = useRef(null)
  const movedRef = useRef(false)
  const [view, setView] = useState({ scale: 1, x: 0, y: 0 })
  const [panning, setPanning] = useState(false)

  // Reset when the match/map changes.
  useEffect(() => setView({ scale: 1, x: 0, y: 0 }), [map])

  const clampView = (v, rect) => {
    const scale = Math.min(MAX_SCALE, Math.max(MIN_SCALE, v.scale))
    const minX = rect.width * (1 - scale)
    const minY = rect.height * (1 - scale)
    return { scale, x: Math.min(0, Math.max(minX, v.x)), y: Math.min(0, Math.max(minY, v.y)) }
  }

  const zoomAt = (clientX, clientY, factor) => {
    const vp = viewportRef.current
    if (!vp) return
    const rect = vp.getBoundingClientRect()
    const px = clientX - rect.left
    const py = clientY - rect.top
    setView((v) => {
      const scale = Math.min(MAX_SCALE, Math.max(MIN_SCALE, v.scale * factor))
      const k = scale / v.scale
      return clampView({ scale, x: px - (px - v.x) * k, y: py - (py - v.y) * k }, rect)
    })
  }

  const zoomFromButton = (factor) => {
    const rect = viewportRef.current?.getBoundingClientRect()
    if (rect) zoomAt(rect.left + rect.width / 2, rect.top + rect.height / 2, factor)
  }

  // Wheel zoom needs a non-passive listener to preventDefault (React's onWheel is passive).
  useEffect(() => {
    const vp = viewportRef.current
    if (!vp) return
    const onWheel = (e) => {
      e.preventDefault()
      zoomAt(e.clientX, e.clientY, e.deltaY < 0 ? 1.15 : 1 / 1.15)
    }
    vp.addEventListener('wheel', onWheel, { passive: false })
    return () => vp.removeEventListener('wheel', onWheel)
  }, [map, loading])

  // Drag to pan (window listeners, not pointer capture, so a dot's click still lands when
  // zoomed). movedRef is reset on every press so a real click after a pan isn't swallowed.
  const onMouseDown = (e) => {
    movedRef.current = false
    if (view.scale <= MIN_SCALE) return // nothing to pan; leave dot clicks alone
    e.preventDefault()
    setPanning(true)
    const start = { sx: e.clientX, sy: e.clientY, ox: view.x, oy: view.y }
    const rect = viewportRef.current.getBoundingClientRect()
    const move = (ev) => {
      const dx = ev.clientX - start.sx
      const dy = ev.clientY - start.sy
      if (Math.abs(dx) + Math.abs(dy) > 4) movedRef.current = true
      setView((v) => clampView({ scale: v.scale, x: start.ox + dx, y: start.oy + dy }, rect))
    }
    const up = () => {
      window.removeEventListener('mousemove', move)
      window.removeEventListener('mouseup', up)
      setPanning(false)
    }
    window.addEventListener('mousemove', move)
    window.addEventListener('mouseup', up)
  }

  const names = [...new Set(players.map((p) => p.name).filter(Boolean))].sort()

  const shown = useMemo(
    () =>
      points.filter(
        (p) =>
          (!f.killer_name || p.killer_name === f.killer_name) &&
          (!f.weapon || p.weapon === f.weapon) &&
          (!f.team || p.team === f.team) &&
          (!f.side || p.side === f.side) &&
          (!f.headshot || p.headshot),
      ),
    [points, f],
  )

  if (error) return <section className="heatmap"><h3>Kill heatmap</h3><p className="error">{t(error)}</p></section>

  return (
    <section className="heatmap">
      <h3>Kill heatmap</h3>

      <div className="search-form">
        <select value={f.killer_name} onChange={(e) => setF({ ...f, killer_name: e.target.value })}>
          <option value="">any player</option>
          {names.map((n) => <option key={n} value={n}>{n}</option>)}
        </select>
        <WeaponSelect value={f.weapon} onChange={(v) => setF({ ...f, weapon: v })} />
        <select value={f.team} onChange={(e) => setF({ ...f, team: e.target.value })}>
          <option value="">any team</option>
          {teams.map((tm) => <option key={tm.side} value={tm.side}>{tm.name}</option>)}
        </select>
        <select value={f.side} onChange={(e) => setF({ ...f, side: e.target.value })}>
          {SIDES.map((s) => <option key={s} value={s}>{s || 'any side'}</option>)}
        </select>
        <label className="chk"><input type="checkbox" checked={f.headshot} onChange={(e) => setF({ ...f, headshot: e.target.checked })} /> HS only</label>
      </div>

      {loading && <p className="muted">Loading positions…</p>}
      {!loading && !hasRadar(map) && (
        <p className="muted">No radar available for {map || 'this map'}.</p>
      )}
      {!loading && hasRadar(map) && (
        <>
          <div className="radar-head">
            <p className="muted">{shown.length} kill{shown.length === 1 ? '' : 's'} shown</p>
            <div className="radar-legend">
              <span className="lg"><span className="dot dot-CT" /> CT</span>
              <span className="lg"><span className="dot dot-T" /> T</span>
              <span className="lg"><span className="dot dot-none dot-hs" /> headshot</span>
            </div>
          </div>
          <div className="radar-wrap">
            <div
              className="radar"
              ref={viewportRef}
              onMouseDown={onMouseDown}
              style={{ cursor: view.scale > 1 ? (panning ? 'grabbing' : 'grab') : 'default' }}
            >
              <div
                className="radar-stage"
                style={{
                  backgroundImage: `url(${radarUrl(map)})`,
                  transform: `translate(${view.x}px, ${view.y}px) scale(${view.scale})`,
                }}
              >
                {shown.map((p, i) => {
                  const pos = toRadar(map, p.x, p.y)
                  if (!pos) return null
                  return (
                    <span
                      key={i}
                      className={`dot dot-${p.side || 'none'}${p.headshot ? ' dot-hs' : ''}${hovered === i ? ' dot-active' : ''}`}
                      style={{
                        left: `${pos.left * 100}%`,
                        top: `${pos.top * 100}%`,
                        // Counter the stage zoom so dots keep a constant screen size — zooming
                        // then spreads dense clusters instead of magnifying the overlap.
                        transform: `scale(${(hovered === i ? 1.9 : 1) / view.scale})`,
                      }}
                      title={`${p.killer_name} → ${p.victim_name} (${p.weapon}, round ${p.round}) — click for hitgroups`}
                      onMouseEnter={() => setHovered(i)}
                      onMouseLeave={() => setHovered(null)}
                      onClick={() => !movedRef.current && setSelected(p)}
                    />
                  )
                })}
              </div>
              <div className="radar-zoom" onMouseDown={(e) => e.stopPropagation()}>
                <button type="button" onClick={() => zoomFromButton(1.4)} disabled={view.scale >= MAX_SCALE} aria-label="Zoom in">+</button>
                <button type="button" onClick={() => zoomFromButton(1 / 1.4)} disabled={view.scale <= MIN_SCALE} aria-label="Zoom out">−</button>
                <button type="button" onClick={() => setView({ scale: 1, x: 0, y: 0 })} disabled={view.scale === 1} aria-label="Reset zoom">⌂</button>
              </div>
            </div>

            {f.killer_name && (
              <ol className="kill-log">
                {shown.length === 0 && <li className="muted">No kills for this filter.</li>}
                {shown.map((p, i) => (
                  <li
                    key={i}
                    className={hovered === i ? 'active' : ''}
                    onMouseEnter={() => setHovered(i)}
                    onMouseLeave={() => setHovered(null)}
                    onClick={() => setSelected(p)}
                  >
                    <span className="kl-round">R{p.round}</span>
                    <span className="kl-victim">{p.victim_name}</span>
                    <span className="kl-weapon">{p.weapon}{p.headshot ? ' • HS' : ''}</span>
                  </li>
                ))}
              </ol>
            )}
          </div>
        </>
      )}

      {selected && (
        <div className="modal-overlay" onClick={() => setSelected(null)}>
          <div className="modal" onClick={(e) => e.stopPropagation()}>
            <button className="modal-close" aria-label="Close" onClick={() => setSelected(null)}>✕</button>
            <h4 className="modal-title">{selected.killer_name} <span className="muted">→</span> {selected.victim_name}</h4>
            <p className="muted">{selected.weapon}{selected.headshot ? ' • headshot' : ''} · round {selected.round}{selected.side ? ` · ${selected.side}` : ''}</p>
            <BodyHitgroups hitgroups={selected.hitgroups} />
            <WatchInGame matchId={matchId} tick={selected.tick} tickRate={tickRate} demo={demo} killer={selected.killer_name} killerSteamId={selected.killer_steam_id} />
          </div>
        </div>
      )}
    </section>
  )
}
