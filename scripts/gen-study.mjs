// Generates docs/STUDY.md from the app's study topics — so the GitHub-readable ledger
// and the in-app Study page never drift. tradeoffs.js is the single source of truth;
// this script only formats it. Run: node scripts/gen-study.mjs
//
// The pitches (plain-English "said out loud" version, pitches.js) are folded in when a
// topic has one, so the doc carries both the ledger and the spoken summary.

import { readFileSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const here = dirname(fileURLToPath(import.meta.url))
const root = resolve(here, '..')

const { TOPICS } = await import(resolve(root, 'frontend/src/features/study/tradeoffs.js'))
const { PITCHES } = await import(resolve(root, 'frontend/src/features/study/pitches.js'))

// Fenced code blocks in body/pitch prose would break the doc's own fences; none exist
// today, but guard anyway by neutralizing stray backtick fences in prose.
const clean = (s) => s.replace(/```/g, '`​``')

const lines = []
lines.push('# The engineering study')
lines.push('')
lines.push('> **Generated from [`frontend/src/features/study/tradeoffs.js`](../frontend/src/features/study/tradeoffs.js) — do not edit by hand.**')
lines.push('> Run `node scripts/gen-study.mjs` after changing the topics. This is the GitHub-readable')
lines.push('> mirror of the in-app Study page.')
lines.push('')
lines.push('Clutchlab is a study of the **seams between services**, not the features. A boundary must')
lines.push('be *earned* — and it is never free. Each decision below is a ledger: what it **gained**, and')
lines.push('what it **cost**.')
lines.push('')

// A table of contents — anchors match GitHub's slugging of the "NN · Title" headings.
lines.push('## Topics')
lines.push('')
for (const t of TOPICS) {
  const slug = `${t.n}--${t.title}`
    .toLowerCase()
    .replace(/[^\w\s-]/g, '')
    .replace(/\s+/g, '-')
  lines.push(`${Number(t.n)}. [${t.title}](#${slug}) — *${t.tag}*`)
}
lines.push('')
lines.push('---')
lines.push('')

for (const t of TOPICS) {
  lines.push(`## ${t.n} · ${clean(t.title)}`)
  lines.push('')
  lines.push(`*${clean(t.tag)}*`)
  lines.push('')
  lines.push(`**${clean(t.summary)}**`)
  lines.push('')

  lines.push('**Gained**')
  lines.push('')
  for (const g of t.gained) lines.push(`- ${clean(g)}`)
  lines.push('')

  lines.push('**Paid**')
  lines.push('')
  for (const p of t.paid) lines.push(`- ${clean(p)}`)
  lines.push('')

  for (const para of t.body) {
    lines.push(clean(para))
    lines.push('')
  }

  if (t.code) {
    lines.push('```')
    lines.push(t.code)
    lines.push('```')
    if (t.codeLabel) {
      lines.push('')
      lines.push(`*${clean(t.codeLabel)}*`)
    }
    lines.push('')
  }

  const pitch = PITCHES[t.id]
  if (pitch) {
    lines.push(`> **In plain English.** ${clean(pitch)}`)
    lines.push('')
  }

  lines.push('---')
  lines.push('')
}

writeFileSync(resolve(root, 'docs/STUDY.md'), lines.join('\n'))
console.log(`Wrote docs/STUDY.md — ${TOPICS.length} topics.`)
