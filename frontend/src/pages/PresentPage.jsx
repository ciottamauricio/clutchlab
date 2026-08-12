import { useCallback, useEffect, useState } from 'react'
import { TOPICS, groupedTopics } from '../features/study/tradeoffs'
import { PITCHES } from '../features/study/pitches'

// A presenter's deck: the same decisions, one at a time, said out loud in plain English.
// This is a personal study/rehearsal tool — walk it with the arrow keys before presenting
// the project. A left menu lists every topic grouped by discipline (mirrors the study page)
// so you can jump straight to one; the stage shows the current topic's spoken pitch.
export default function PresentPage() {
  const [i, setI] = useState(0)
  const last = TOPICS.length - 1
  const topic = TOPICS[i]
  const groups = groupedTopics()

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
      <nav className="deck-menu" aria-label="Topics by discipline">
        {groups.map(({ group, topics }) => (
          <div className="deck-menu-group" key={group}>
            <span className="deck-menu-label">{group}</span>
            <ul>
              {topics.map((t) => {
                const idx = TOPICS.indexOf(t)
                const current = idx === i
                return (
                  <li key={t.id}>
                    <button
                      type="button"
                      className={`deck-menu-item${current ? ' current' : ''}`}
                      aria-current={current ? 'true' : undefined}
                      onClick={() => go(idx)}
                    >
                      <span className="deck-menu-n">{t.n}</span>
                      <span className="deck-menu-title">{t.title}</span>
                    </button>
                  </li>
                )
              })}
            </ul>
          </div>
        ))}
      </nav>

      <div className="deck-main">
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
          <span className="deck-progress muted">{topic.group}</span>
          <button type="button" className="deck-arrow" onClick={() => go(i + 1)} disabled={i === last}>
            Next ›
          </button>
        </nav>

        <p className="deck-hint muted">Use ← → to move · pick a topic from the menu · this is your rehearsal deck</p>
      </div>
    </section>
  )
}
