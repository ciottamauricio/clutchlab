import { useState } from 'react'
import { createTactic } from './api'
import { t } from '../../lib/i18n'

export default function CreateTactic({ onCreated }) {
  const [name, setName] = useState('')
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)

  const submit = async (e) => {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      const tactic = await createTactic(name)
      setName('')
      onCreated?.(tactic)
    } catch (err) {
      setError(err.code ?? 'error.unknown')
    } finally {
      setBusy(false)
    }
  }

  return (
    <form className="inline-form" onSubmit={submit}>
      <input placeholder="New tactic name" value={name} onChange={(e) => setName(e.target.value)} />
      <button type="submit" disabled={!name || busy}>Create tactic</button>
      {error && <p className="error">{t(error)}</p>}
    </form>
  )
}
