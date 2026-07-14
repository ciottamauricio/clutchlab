import { useState } from 'react'
import { useAuth } from '../auth/AuthContext'
import { rsvpTraining, createAssignment, toggleAssignmentDone, deleteAssignment } from './api'
import { NADE_TYPES, nadeLabel, nadeUrl } from './nades'
import { mapLabel } from '../matches/format'

// One homework chip: opens the study site; the assignee gets a done-toggle in front.
export function PrepChip({ training, assignment, onChanged }) {
  const done = Boolean(assignment.done_at)

  return (
    <span className={`prep-chip${done ? ' done' : ''}`}>
      {assignment.can?.complete && (
        <button
          type="button"
          className="prep-done"
          title={done ? 'Mark as not studied' : 'Mark as studied'}
          aria-pressed={done}
          onClick={async () => {
            await toggleAssignmentDone(training.id, assignment.id, !done)
            onChanged?.()
          }}
        >
          {done ? '✓' : '○'}
        </button>
      )}
      {!assignment.can?.complete && done && <span className="prep-done-mark" aria-label="studied">✓</span>}
      <a href={nadeUrl(assignment.map, assignment.nade_type)} target="_blank" rel="noreferrer">
        {mapLabel(assignment.map)} {nadeLabel(assignment.nade_type)} ↗
      </a>
      {training.can?.manage && (
        <button
          type="button"
          className="prep-remove"
          title="Remove this homework"
          onClick={async () => {
            await deleteAssignment(training.id, assignment.id)
            onChanged?.()
          }}
        >
          ×
        </button>
      )}
    </span>
  )
}

// The current user's homework for a session — the hero's "your prep" row.
export function MyPrep({ training, onChanged }) {
  const { user } = useAuth()
  const mine = (training.assignments ?? []).filter((a) => a.user_id === user?.id)
  if (mine.length === 0) return null

  return (
    <div className="prep-mine">
      <span className="tr-picker-label">Your prep</span>
      <div className="prep-chips">
        {mine.map((a) => <PrepChip key={a.id} training={training} assignment={a} onChanged={onChanged} />)}
      </div>
    </div>
  )
}

// Answer your own invite. Current answer stays highlighted; answers are changeable.
export function RsvpButtons({ training, onChanged }) {
  const { user } = useAuth()
  if (!training.can?.rsvp) return null
  const mine = training.players?.find((p) => p.id === user?.id)?.rsvp ?? null

  const answer = async (going) => {
    await rsvpTraining(training.id, going)
    onChanged?.()
  }

  return (
    <div className="rsvp" role="group" aria-label="Will you attend?">
      <button type="button" className={`rsvp-btn rsvp-in${mine === 'in' ? ' on' : ''}`} onClick={() => answer(true)}>
        ✓ I&apos;m in
      </button>
      <button type="button" className={`rsvp-btn rsvp-out${mine === 'out' ? ' on' : ''}`} onClick={() => answer(false)}>
        ✗ Can&apos;t make it
      </button>
    </div>
  )
}

const RSVP_MARK = { in: '✓', out: '✗' }

// The whole team's prep + attendance, per roster player. Managers also get the
// assign controls: pick a map once, then click a nade type next to a player.
export default function TrainingPrep({ training, onChanged }) {
  const roster = training.players ?? []
  const assignments = training.assignments ?? []
  const maps = [...new Set((training.tactics ?? []).map((tc) => tc.map).filter(Boolean))]
  const [map, setMap] = useState(maps[0] ?? 'de_mirage')
  const canManage = Boolean(training.can?.manage)

  if (roster.length === 0) return null

  return (
    <div className="prep">
      <div className="prep-head">
        <span className="tr-picker-label">Attendance &amp; prep</span>
        {canManage && (
          <label className="prep-map">
            assign on
            <select value={map} onChange={(e) => setMap(e.target.value)}>
              {[...new Set([map, ...maps, 'de_mirage', 'de_inferno', 'de_dust2', 'de_nuke', 'de_ancient', 'de_anubis', 'de_overpass', 'de_train', 'de_vertigo'])].map((m) => (
                <option key={m} value={m}>{mapLabel(m)}</option>
              ))}
            </select>
          </label>
        )}
      </div>

      <ul className="prep-roster">
        {roster.map((p) => {
          const theirs = assignments.filter((a) => a.user_id === p.id)
          return (
            <li key={p.id} className="prep-row">
              <span className={`prep-rsvp prep-rsvp-${p.rsvp ?? 'none'}`} title={p.rsvp ? `RSVP: ${p.rsvp}` : 'No answer yet'}>
                {RSVP_MARK[p.rsvp] ?? '·'}
              </span>
              <span className="prep-name">{p.name}</span>
              <span className="prep-chips">
                {theirs.map((a) => <PrepChip key={a.id} training={training} assignment={a} onChanged={onChanged} />)}
                {canManage && NADE_TYPES.map((n) => (
                  <button
                    key={n.value}
                    type="button"
                    className="prep-add"
                    title={`Assign ${mapLabel(map)} ${n.label.toLowerCase()}`}
                    onClick={async () => {
                      await createAssignment(training.id, { user_id: p.id, map, nade_type: n.value })
                      onChanged?.()
                    }}
                  >
                    +{n.short}
                  </button>
                ))}
              </span>
            </li>
          )
        })}
      </ul>
    </div>
  )
}
