import { useState } from 'react'
import { useDocsQuestion } from './api'
import { t } from '../../lib/i18n'

// Split an answer on [doc:path#heading] citations. Unlike the analyst's [match:N] chips
// these don't navigate: the markdown isn't served to the browser, so a button here would
// be a link to nowhere. They mark WHICH document backed the claim, which is the part that
// makes the answer checkable — the reader has the repo.
function renderAnswer(text) {
  const parts = text.split(/\[doc:([^\]]+)\]/g)
  return parts.map((part, i) => {
    if (i % 2 === 0) return <span key={i}>{part}</span>
    const [path, heading] = part.split('#')
    return (
      <span key={i} className="docs-cite" title={heading ? `${path} — ${heading}` : path}>
        {heading || path}
      </span>
    )
  })
}

// Chosen to show both sides of what the corpus can do. The first three retrieve well;
// the last one names a symbol, which is the documented weak spot — the embedder knows
// prose, and an identifier is not a word to it. Leaving it in is the honest demo.
const SUGGESTIONS = [
  'Why is the parse queue a plain Redis list?',
  'Why was Go chosen for the worker instead of PHP?',
  'What are the business rules for scheduling a training?',
  'What does DocRetriever do?',
]

export default function AskDocs() {
  const [question, setQuestion] = useState('')
  const { answer, sources, asked, loading, error, ask } = useDocsQuestion()

  const submit = (e) => {
    e.preventDefault()
    const q = question.trim()
    if (q.length >= 5 && !loading) ask(q)
  }

  const runSuggestion = (s) => {
    setQuestion(s)
    if (!loading) ask(s)
  }

  const top = sources[0]?.similarity ?? 0

  return (
    <section className="docs-ask">
      <div className="docs-ask-head">
        <h2 className="docs-ask-title">Ask the documentation</h2>
        <p className="docs-ask-sub">
          A local 7B model answering from this repo's own markdown — the architecture notes, the
          per-service conventions, and the per-domain business rules. It retrieves the nearest
          sections and is instructed to answer from those alone, so a question the docs don't
          cover should get a refusal rather than a guess.
        </p>
      </div>

      <form className="docs-ask-form" onSubmit={submit}>
        <input
          type="text"
          value={question}
          maxLength={500}
          placeholder="Why is the parse queue a plain Redis list?"
          aria-label="Ask a question about this project's architecture"
          onChange={(e) => setQuestion(e.target.value)}
        />
        <button type="submit" disabled={loading || question.trim().length < 5}>
          {loading ? 'Reading the docs…' : 'Ask'}
        </button>
      </form>

      <p className="docs-ask-hints">
        {SUGGESTIONS.map((s) => (
          <button key={s} type="button" className="docs-hint" onClick={() => runSuggestion(s)} disabled={loading}>
            {s}
          </button>
        ))}
      </p>

      {/* The first question after an idle spell pays ~2s to load the embedding model into
          VRAM, then generation runs to ~15s. Saying so beats a spinner that looks stuck. */}
      {loading && (
        <p className="docs-ask-wait" aria-live="polite">
          Retrieving sections, then generating locally — usually 5–20s, longer if the model
          has to load into VRAM first.
        </p>
      )}

      {error && <p className="error">{t(error)}</p>}

      {answer !== null && !error && !loading && (
        <div className="docs-answer" aria-live="polite">
          <p className="docs-q">{asked}</p>
          <p className="docs-a">{renderAnswer(answer)}</p>

          {sources.length > 0 && (
            <div className="docs-sources">
              <span className="docs-sources-label">
                Retrieved sections
                {/* The score is the study, not decoration: it is what separates "the docs
                    answered this" from "the model improvised over weak matches". */}
                <span className={`docs-score-note${top < 0.55 ? ' weak' : ''}`}>
                  best match {top.toFixed(3)}
                  {top < 0.55 && ' — weak, the corpus likely does not cover this'}
                </span>
              </span>
              <ol>
                {sources.map((s) => (
                  <li key={`${s.path}#${s.heading}`}>
                    <span className="docs-source-score">{s.similarity.toFixed(3)}</span>
                    <span className="docs-source-path">{s.path}</span>
                    <span className="docs-source-heading">{s.heading}</span>
                  </li>
                ))}
              </ol>
            </div>
          )}
        </div>
      )}
    </section>
  )
}
