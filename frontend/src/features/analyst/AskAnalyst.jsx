import { useState } from 'react'
import { useAnalyst } from './api'
import { t } from '../../lib/i18n'

// Split an answer on [match:N] citations so each becomes a chip that opens that
// match in the dashboard — the answer stays checkable against the real data.
function renderAnswer(text, onOpenMatch) {
  const parts = text.split(/\[match:(\d+)\]/g)
  return parts.map((part, i) =>
    i % 2 === 1 ? (
      <button
        key={i}
        type="button"
        className="an-cite"
        onClick={() => onOpenMatch(Number(part))}
        title={`Open match #${part}`}
      >
        #{part}
      </button>
    ) : (
      <span key={i}>{part}</span>
    ),
  )
}

const SUGGESTIONS = [
  'How do we usually perform on Mirage?',
  'Who gets the most opening kills?',
  'Which map should we practice next?',
]

export default function AskAnalyst({ onOpenMatch }) {
  const [question, setQuestion] = useState('')
  const { answer, asked, loading, error, ask } = useAnalyst()

  const submit = (e) => {
    e.preventDefault()
    const q = question.trim()
    if (q.length >= 5 && !loading) ask(q)
  }

  return (
    <section className="analyst">
      <form className="an-ask" onSubmit={submit}>
        <span className="an-label">Analyst</span>
        <input
          type="text"
          value={question}
          maxLength={500}
          placeholder="Ask about your matches…"
          aria-label="Ask the analyst about your matches"
          onChange={(e) => setQuestion(e.target.value)}
        />
        <button type="submit" disabled={loading || question.trim().length < 5}>
          {loading ? 'Reading demos…' : 'Ask'}
        </button>
      </form>

      {error && <p className="error">{t(error)}</p>}

      {answer !== null && !error && (
        <div className="an-answer" aria-live="polite">
          <p className="an-q">{asked}</p>
          <p className="an-a">{renderAnswer(answer, onOpenMatch)}</p>
        </div>
      )}

      {answer === null && !error && !loading && (
        <p className="an-hints">
          {SUGGESTIONS.map((s) => (
            <button key={s} type="button" className="an-hint" onClick={() => { setQuestion(s); ask(s) }}>
              {s}
            </button>
          ))}
        </p>
      )}
    </section>
  )
}
