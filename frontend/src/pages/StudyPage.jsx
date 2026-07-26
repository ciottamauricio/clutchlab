import { useState } from 'react'
import { Link } from 'react-router-dom'
import { TOPICS } from '../features/study/tradeoffs'
import Topology from '../features/study/Topology'

// The engineering study: every architectural decision as a ledger of what it bought and what
// it cost. Topics collapse; clicking a header toggles its explanation. Grounded in the repo's
// own architecture docs — this is the real shape of the system.
export default function StudyPage() {
  const [open, setOpen] = useState(() => new Set())

  const toggle = (id) =>
    setOpen((prev) => {
      const next = new Set(prev)
      next.has(id) ? next.delete(id) : next.add(id)
      return next
    })

  const allOpen = open.size === TOPICS.length
  const toggleAll = () => setOpen(allOpen ? new Set() : new Set(TOPICS.map((t) => t.id)))

  return (
    <section className="study">
      <header className="study-hero">
        <span className="study-eyebrow">The engineering study</span>
        <h1 className="study-title">Every boundary, and what it cost</h1>
        <p className="study-lede">
          Clutchlab is a study of the <strong>seams between services</strong>, not the features. A boundary
          must be <em>earned</em> — and it is never free. Each decision below is a ledger: what it bought on
          the left, what it charged on the right.
        </p>
        <div className="study-actions">
          <button type="button" className="study-expand" onClick={toggleAll}>
            {allOpen ? 'Collapse all' : 'Expand all'}
          </button>
          <Link to="/present" className="study-present-link">Present mode →</Link>
        </div>
      </header>

      <Topology />

      <ol className="study-list">
        {TOPICS.map((topic) => {
          const isOpen = open.has(topic.id)
          return (
            <li key={topic.id} className={`study-topic${isOpen ? ' open' : ''}`}>
              <button
                type="button"
                className="study-topic-head"
                onClick={() => toggle(topic.id)}
                aria-expanded={isOpen}
              >
                <span className="study-n">{topic.n}</span>
                <span className="study-topic-main">
                  <span className="study-topic-title">
                    {topic.title}
                    <span className="study-topic-tag">{topic.tag}</span>
                  </span>
                  <span className="study-topic-summary">{topic.summary}</span>
                </span>
                <span className="study-chevron" aria-hidden="true">{isOpen ? '−' : '+'}</span>
              </button>

              {isOpen && (
                <div className="study-topic-body">
                  <div className="study-ledger">
                    <div className="study-col study-gained">
                      <span className="study-col-label">Gained</span>
                      <ul>
                        {topic.gained.map((g, i) => <li key={i}>{g}</li>)}
                      </ul>
                    </div>
                    <div className="study-col study-paid">
                      <span className="study-col-label">Paid</span>
                      <ul>
                        {topic.paid.map((p, i) => <li key={i}>{p}</li>)}
                      </ul>
                    </div>
                  </div>

                  {topic.body.map((para, i) => (
                    <p className="study-prose" key={i}>{para}</p>
                  ))}

                  {topic.code && (
                    <figure className="study-code">
                      {topic.codeLabel && <figcaption>{topic.codeLabel}</figcaption>}
                      <pre>{topic.code}</pre>
                    </figure>
                  )}
                </div>
              )}
            </li>
          )
        })}
      </ol>

      <footer className="study-foot">
        <p className="muted">
          monolith-ish → split under justified pressure → feel the tradeoffs → decide what was worth it.
        </p>
      </footer>
    </section>
  )
}
