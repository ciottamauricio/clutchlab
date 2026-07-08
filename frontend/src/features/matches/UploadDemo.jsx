import { useRef, useState } from 'react'
import { useUploadDemo } from './api'
import { useTeams } from '../teams/api'
import { t } from '../../lib/i18n'

// Only these team roles may file a match under a team (mirrors TeamPolicy::uploadMatch).
const CAN_UPLOAD = new Set(['owner', 'igl'])

export default function UploadDemo({ onUploaded }) {
  const [file, setFile] = useState(null)
  const [teamId, setTeamId] = useState('')
  const inputRef = useRef(null)
  const { teams } = useTeams()

  const uploadable = teams.filter((team) => CAN_UPLOAD.has(team.my_role))

  const { upload, uploading, error } = useUploadDemo((match) => {
    setFile(null)
    if (inputRef.current) inputRef.current.value = ''
    onUploaded?.(match)
  })

  const submit = (e) => {
    e.preventDefault()
    if (file) upload(file, teamId || null)
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
        <select value={teamId} onChange={(e) => setTeamId(e.target.value)} aria-label="Team">
          <option value="">Private — just me</option>
          {uploadable.map((team) => (
            <option key={team.id} value={team.id}>{team.name}</option>
          ))}
        </select>
      )}
      <button type="submit" disabled={!file || uploading}>
        {uploading ? 'Uploading…' : 'Upload demo'}
      </button>
      {error && <p className="error">{t(error)}</p>}
    </form>
  )
}
