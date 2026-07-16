import { useRef, useState } from 'react'
import { useUploadDemo } from './api'
import { useTeams } from '../teams/api'
import { t } from '../../lib/i18n'

// Only these team roles may file a match under a team (mirrors TeamPolicy::uploadMatch).
const CAN_UPLOAD = new Set(['owner', 'igl'])

export default function UploadDemo({ onUploaded }) {
  const [file, setFile] = useState(null)
  // null = untouched. Teams arrive async, so the "first team" default is derived at
  // render rather than set as state; an explicit choice (including Private) sticks.
  const [teamId, setTeamId] = useState(null)
  const inputRef = useRef(null)
  const { teams } = useTeams()

  const uploadable = teams.filter((team) => CAN_UPLOAD.has(team.my_role))
  const effectiveTeamId = teamId ?? (uploadable[0] ? String(uploadable[0].id) : '')

  const { upload, uploading, error } = useUploadDemo((match) => {
    setFile(null)
    if (inputRef.current) inputRef.current.value = ''
    onUploaded?.(match)
  })

  const submit = (e) => {
    e.preventDefault()
    if (file) upload(file, effectiveTeamId || null)
  }

  return (
    <form className="upload" onSubmit={submit}>
      <input
        ref={inputRef}
        type="file"
        accept=".dem"
        onChange={(e) => setFile(e.target.files?.[0] ?? null)}
      />
      {uploadable.length > 0 && (
        <select value={effectiveTeamId} onChange={(e) => setTeamId(e.target.value)} aria-label="Team">
          {uploadable.map((team) => (
            <option key={team.id} value={team.id}>{team.name}</option>
          ))}
          <option value="">Private — just me</option>
        </select>
      )}
      <button type="submit" disabled={!file || uploading}>
        {uploading ? 'Uploading…' : 'Upload demo'}
      </button>
      {error && <p className="error">{t(error)}</p>}
    </form>
  )
}
