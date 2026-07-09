import { useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react'
import { createPortal } from 'react-dom'

// A searchable single-select (Select2-style typeahead), built native for React — no jQuery.
// options: [{ value, label, hint?, disabled? }]. The dropdown renders in a portal with fixed
// positioning so it's never clipped by an overflow/scroll ancestor (e.g. a table wrapper).
export default function SearchableSelect({ value, onChange, options, placeholder = 'Select…', disabled = false }) {
  const [open, setOpen] = useState(false)
  const [query, setQuery] = useState('')
  const [active, setActive] = useState(0)
  const [rect, setRect] = useState(null)
  const rootRef = useRef(null)
  const panelRef = useRef(null)
  const inputRef = useRef(null)

  const selected = options.find((o) => o.value === value)

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase()
    return q ? options.filter((o) => `${o.label} ${o.hint ?? ''}`.toLowerCase().includes(q)) : options
  }, [options, query])

  const openMenu = () => {
    if (disabled) return
    const r = rootRef.current?.getBoundingClientRect()
    if (r) setRect({ top: r.bottom + 4, left: r.left, width: r.width })
    setQuery('')
    setActive(0)
    setOpen(true)
  }

  useLayoutEffect(() => {
    if (open) inputRef.current?.focus()
  }, [open])

  // Close on outside click, or on scroll/resize (the fixed panel would otherwise drift).
  useEffect(() => {
    if (!open) return
    const onDown = (e) => {
      if (rootRef.current?.contains(e.target) || panelRef.current?.contains(e.target)) return
      setOpen(false)
    }
    const close = () => setOpen(false)
    document.addEventListener('mousedown', onDown)
    window.addEventListener('scroll', close, true)
    window.addEventListener('resize', close)
    return () => {
      document.removeEventListener('mousedown', onDown)
      window.removeEventListener('scroll', close, true)
      window.removeEventListener('resize', close)
    }
  }, [open])

  const choose = (opt) => {
    if (!opt || opt.disabled) return
    onChange(opt.value)
    setOpen(false)
  }

  const onKeyDown = (e) => {
    if (e.key === 'ArrowDown') { e.preventDefault(); setActive((i) => step(filtered, i, 1)) }
    else if (e.key === 'ArrowUp') { e.preventDefault(); setActive((i) => step(filtered, i, -1)) }
    else if (e.key === 'Enter') { e.preventDefault(); choose(filtered[active]) }
    else if (e.key === 'Escape') { setOpen(false) }
  }

  return (
    <div className={`ss${disabled ? ' ss-disabled' : ''}`} ref={rootRef}>
      <button
        type="button"
        className="ss-trigger"
        disabled={disabled}
        onClick={() => (open ? setOpen(false) : openMenu())}
        aria-haspopup="listbox"
        aria-expanded={open}
      >
        <span className={selected ? 'ss-value' : 'ss-placeholder'}>{selected?.label ?? placeholder}</span>
        <span className="ss-caret" aria-hidden="true">▾</span>
      </button>

      {open && rect && createPortal(
        <div className="ss-panel" ref={panelRef} style={{ top: rect.top, left: rect.left, width: rect.width }}>
          <input
            ref={inputRef}
            className="ss-search"
            value={query}
            onChange={(e) => { setQuery(e.target.value); setActive(0) }}
            onKeyDown={onKeyDown}
            placeholder="Search…"
            aria-label="Search"
          />
          <ul className="ss-list" role="listbox">
            {filtered.length === 0 && <li className="ss-empty">No matches</li>}
            {filtered.map((o, i) => (
              <li
                key={o.value === '' ? '__none' : o.value}
                role="option"
                aria-selected={o.value === value}
                className={`ss-option${i === active ? ' ss-active' : ''}${o.disabled ? ' ss-opt-disabled' : ''}${o.value === value ? ' ss-selected' : ''}`}
                onMouseEnter={() => setActive(i)}
                onMouseDown={(e) => { e.preventDefault(); choose(o) }}
              >
                <span className="ss-opt-label">{o.label}</span>
                {o.hint && <span className="ss-hint">{o.hint}</span>}
              </li>
            ))}
          </ul>
        </div>,
        document.body,
      )}
    </div>
  )
}

// Next non-disabled index in `dir`, wrapping.
function step(list, from, dir) {
  const n = list.length
  if (!n) return 0
  let i = from
  for (let s = 0; s < n; s++) {
    i = (i + dir + n) % n
    if (!list[i].disabled) return i
  }
  return from
}
