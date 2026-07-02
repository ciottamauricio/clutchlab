import { useRef, useState } from 'react'
import { useUploadDemo } from './api'
import { t } from '../../lib/i18n'

export default function UploadDemo({ onUploaded }) {
  const [file, setFile] = useState(null)
  const inputRef = useRef(null)

  const { upload, uploading, error } = useUploadDemo((match) => {
    setFile(null)
    if (inputRef.current) inputRef.current.value = ''
    onUploaded?.(match)
  })

  const submit = (e) => {
    e.preventDefault()
    if (file) upload(file)
  }

  return (
    <form className="upload" onSubmit={submit}>
      <input
        ref={inputRef}
        type="file"
        accept=".dem"
        onChange={(e) => setFile(e.target.files?.[0] ?? null)}
      />
      <button type="submit" disabled={!file || uploading}>
        {uploading ? 'Uploading…' : 'Upload demo'}
      </button>
      {error && <p className="error">{t(error)}</p>}
    </form>
  )
}
