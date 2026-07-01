import { useCallback, useState } from 'react'
import { uploadDemo } from '../lib/api'

export function useUploadDemo(onUploaded) {
  const [uploading, setUploading] = useState(false)
  const [error, setError] = useState(null)

  const upload = useCallback(async (file) => {
    setUploading(true)
    setError(null)
    try {
      const match = await uploadDemo(file)
      onUploaded?.(match)
      return match
    } catch (e) {
      setError(e.code ?? 'error.unknown')
    } finally {
      setUploading(false)
    }
  }, [onUploaded])

  return { upload, uploading, error }
}
