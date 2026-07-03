import { Fragment, useState } from 'react'
import BodyHitgroups from '../../components/BodyHitgroups'
import WatchInGame from '../matches/WatchInGame'

export default function KillsTable({ hits }) {
  // id of the row whose body hitgroup map is expanded (null = none).
  const [openId, setOpenId] = useState(null)

  if (!hits.length) return null

  const toggle = (id) => setOpenId((cur) => (cur === id ? null : id))

  return (
    <table className="members kills-table">
      <thead>
        <tr>
          <th>Round</th>
          <th>Killer</th>
          <th>Victim</th>
          <th>Weapon</th>
          <th>HS</th>
          <th>Opening</th>
          <th>Side</th>
        </tr>
      </thead>
      <tbody>
        {hits.map((k) => (
          <Fragment key={k.id}>
            <tr className={`kill-row${openId === k.id ? ' open' : ''}`} onClick={() => toggle(k.id)}>
              <td>{k.round}</td>
              <td>{k.killer_name}</td>
              <td>{k.victim_name}</td>
              <td>{k.weapon}</td>
              <td>{k.headshot ? '✓' : ''}</td>
              <td>{k.opening ? '✓' : ''}</td>
              <td>{k.side}</td>
            </tr>
            {openId === k.id && (
              <tr className="kill-expand">
                <td colSpan={7}>
                  <div className="kill-expand-body">
                    <BodyHitgroups hitgroups={k.hitgroups} />
                    <div className="kill-expand-watch">
                      <WatchInGame matchId={k.match_id} tick={k.tick} tickRate={k.tick_rate} demo={k.demo} killer={k.killer_name} killerSteamId={k.killer_steam_id} />
                    </div>
                  </div>
                </td>
              </tr>
            )}
          </Fragment>
        ))}
      </tbody>
    </table>
  )
}
