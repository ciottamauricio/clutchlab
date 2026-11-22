import { useRef, useState } from 'react'
import { useUploadDemo } from './api'
import { useTeams } from '../teams/api'
import { t } from '../../lib/i18n'
import { guessPlatform, guessPlayedAt } from './demoFilename'

// Only these team roles may file a match under a team (mirrors TeamPolicy::uploadMatch).
const CAN_UPLOAD = new Set(['owner', 'igl'])
// Where a match can be played (mirrors GameMatch::PLATFORMS); labels via t('platform.<code>').
const PLATFORMS = ['steam', 'faceit', 'gamersclub', 'other']

// A <input type="datetime-local"> speaks the viewer's local wall clock ("2026-11-21T21:30"),
// while the API speaks instants. '' stays '' (nothing to convert).
function toLocalInput(value) {
  if (!value) return ''
  const d = new Date(value)
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

export default function UploadDemo({ onUploaded }) {
  const [file, setFile] = useState(null)
  // null = untouched. Teams arrive async, so the "first team" default is derived at
  // render rather than set as state; an explicit choice (including Private) sticks.
  const [teamId, setTeamId] = useState(null)
  // null = follow the guess from the chosen file's name; picking a new file drops an
  // explicit choice, since the guess (and likely the answer) is per file.
  const [platform, setPlatform] = useState(null)
  const [playedAt, setPlayedAt] = useState(null)
  const inputRef = useRef(null)
  const { teams } = useTeams()

  const uploadable = teams.filter((team) => CAN_UPLOAD.has(team.my_role))
  const effectiveTeamId = teamId ?? (uploadable[0] ? String(uploadable[0].id) : '')
  const effectivePlatform = platform ?? guessPlatform(file?.name)
  const effectivePlayedAt = playedAt ?? toLocalInput(guessPlayedAt(file?.name))
  const ready = file && effectivePlatform && effectivePlayedAt

  const resetGuesses = () => {
    setPlatform(null)
    setPlayedAt(null)
  }

  const { upload, uploading, error } = useUploadDemo((match) => {
    setFile(null)
    resetGuesses()
    if (inputRef.current) inputRef.current.value = ''
    onUploaded?.(match)
  })

  const submit = (e) => {
    e.preventDefault()
    if (ready) {
      upload(file, effectiveTeamId || null, effectivePlatform, new Date(effectivePlayedAt).toISOString())
    }
  }

  return (
    <form className="upload" onSubmit={submit}>
      <input
        ref={inputRef}
        type="file"
        accept=".dem"
        onChange={(e) => {
          setFile(e.target.files?.[0] ?? null)
          resetGuesses()
        }}
      />
      <select value={effectivePlatform} onChange={(e) => setPlatform(e.target.value)} aria-label="Platform">
        <option value="" disabled>Where was it played?</option>
        {PLATFORMS.map((code) => (
          <option key={code} value={code}>{t(`platform.${code}`)}</option>
        ))}
      </select>
      <label className="upload-when">
        Played
        <input
          type="datetime-local"
          value={effectivePlayedAt}
          max={toLocalInput(new Date())}
          onChange={(e) => setPlayedAt(e.target.value)}
        />
      </label>
      {uploadable.length > 0 && (
        <select value={effectiveTeamId} onChange={(e) => setTeamId(e.target.value)} aria-label="Team">
          {uploadable.map((team) => (
            <option key={team.id} value={team.id}>{team.name}</option>
          ))}
          <option value="">Private — just me</option>
        </select>
      )}
      <button type="submit" disabled={!ready || uploading}>
        {uploading ? 'Uploading…' : 'Upload demo'}
      </button>
      {error && <p className="error">{t(error)}</p>}
    </form>
  )
}
