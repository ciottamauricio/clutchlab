import { useState } from 'react'
import { usePlayerCatalog, addPlayer, removePlayer } from './api'
import SearchableSelect from '../../components/SearchableSelect'
import { t } from '../../lib/i18n'

// The in-game roster: pick players (by steam_id) from the catalog of everyone seen across
// your matches. Separate from the login members list — a rostered player needs no account.
export default function TeamRoster({ teamId, roster, canManage, onChanged }) {
  const { players: catalog } = usePlayerCatalog()
  const [steamId, setSteamId] = useState('')
  const [nickname, setNickname] = useState('')
  const [error, setError] = useState(null)

  const nameOf = (id) => catalog.find((c) => c.steam_id === id)?.name || id
  const rostered = new Set(roster.map((p) => p.steam_id))
  const available = catalog.filter((c) => !rostered.has(c.steam_id))

  const add = async (e) => {
    e.preventDefault()
    setError(null)
    try {
      await addPlayer(teamId, steamId, nickname)
      setSteamId('')
      setNickname('')
      onChanged()
    } catch (err) {
      setError(err.code ?? 'error.unknown')
    }
  }

  const remove = async (id) => {
    await removePlayer(teamId, id)
    onChanged()
  }

  return (
    <div className="roster">
      <h3>Roster</h3>
      {roster.length === 0 ? (
        <p className="muted">No players yet — add them from your matches below.</p>
      ) : (
        <table className="members">
          <thead>
            <tr>
              <th>Player</th>
              <th>SteamID</th>
              {canManage && <th />}
            </tr>
          </thead>
          <tbody>
            {roster.map((p) => (
              <tr key={p.steam_id}>
                <td>{p.nickname || nameOf(p.steam_id)}</td>
                <td className="mono-dim">{p.steam_id}</td>
                {canManage && (
                  <td>
                    <button type="button" className="link-btn" onClick={() => remove(p.steam_id)}>remove</button>
                  </td>
                )}
              </tr>
            ))}
          </tbody>
        </table>
      )}

      {canManage && (
        <form className="inline-form" onSubmit={add}>
          <SearchableSelect
            value={steamId}
            onChange={setSteamId}
            options={available.map((c) => ({
              value: c.steam_id,
              label: c.name,
              hint: `${c.match_count} ${c.match_count === 1 ? 'match' : 'matches'}`,
            }))}
            placeholder="Add a player…"
          />
          <input
            type="text"
            placeholder="nickname (optional)"
            value={nickname}
            onChange={(e) => setNickname(e.target.value)}
          />
          <button type="submit" disabled={!steamId}>Add to roster</button>
          {error && <p className="error">{t(error)}</p>}
        </form>
      )}
    </div>
  )
}
