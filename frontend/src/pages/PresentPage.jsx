import { useCallback, useEffect, useState } from 'react'
import { TOPICS } from '../features/study/tradeoffs'
import { PITCHES } from '../features/study/pitches'

// A presenter's deck: the same 17 decisions, one at a time, said out loud in plain English.
// This is a personal study/rehearsal tool — walk it with the arrow keys before presenting
// the project. It reuses the study topics (title, tag, summary) and adds the spoken pitch.
export default function PresentPage() {
  const [i, setI] = useState(0)
  const last = TOPICS.length - 1
  const topic = TOPICS[i]

  const go = useCallback((next) => setI(Math.min(last, Math.max(0, next))), [last])

  useEffect(() => {
    const onKey = (e) => {
      if (e.key === 'ArrowRight' || e.key === 'PageDown') go(i + 1)
      if (e.key === 'ArrowLeft' || e.key === 'PageUp') go(i - 1)
      if (e.key === 'Home') go(0)
      if (e.key === 'End') go(last)
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [i, go, last])

  return (
    <section className="deck">
      <header className="deck-bar">
        <span className="deck-kicker">Present · say it out loud</span>
        <span className="deck-count">{topic.n} / {TOPICS.length}</span>
      </header>

      <div className="deck-stage">
        <article className="deck-card" key={topic.id}>
          <span className="deck-n">{topic.n} · {topic.group}</span>
          <h1 className="deck-title">{topic.title}</h1>
          <span className="deck-tag">{topic.tag}</span>
          <p className="deck-summary">{topic.summary}</p>
          <p className="deck-pitch">{PITCHES[topic.id] ?? topic.summary}</p>
        </article>
      </div>

      <nav className="deck-controls">
        <button type="button" className="deck-arrow" onClick={() => go(i - 1)} disabled={i === 0}>
          ‹ Prev
        </button>
        <ol className="deck-rail" aria-label="Jump to a topic">
          {TOPICS.map((t, idx) => (
            <li key={t.id}>
              <button
                type="button"
                className={`deck-dot${idx === i ? ' current' : ''}`}
                aria-label={`${t.n} — ${t.title}`}
                aria-current={idx === i ? 'true' : undefined}
                title={`${t.n} · ${t.title}`}
                onClick={() => go(idx)}
              >
                {t.n}
              </button>
            </li>
          ))}
        </ol>
        <button type="button" className="deck-arrow" onClick={() => go(i + 1)} disabled={i === last}>
          Next ›
        </button>
      </nav>

      <p className="deck-hint muted">Use ← → to move · click a number to jump · this is your rehearsal deck</p>
    </section>
  )
}
