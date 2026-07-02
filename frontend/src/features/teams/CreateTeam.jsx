import { useState } from 'react'
import { createTeam } from './api'
import { t } from '../../lib/i18n'

export default function CreateTeam({ onCreated }) {
  const [name, setName] = useState('')
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)

  const submit = async (e) => {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      const team = await createTeam(name)
      setName('')
      onCreated?.(team)
    } catch (err) {
      setError(err.code ?? 'error.unknown')
    } finally {
      setBusy(false)
    }
  }

  return (
    <form className="inline-form" onSubmit={submit}>
      <input placeholder="New team name" value={name} onChange={(e) => setName(e.target.value)} />
      <button type="submit" disabled={!name || busy}>Create team</button>
      {error && <p className="error">{t(error)}</p>}
    </form>
  )
}
