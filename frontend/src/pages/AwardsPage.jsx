import { useMemo, useState } from 'react'
import { useAwards } from '../features/awards/api'
import { useTeams } from '../features/teams/api'
import { useMatches } from '../features/matches/api'
import { WEAPONS } from '../features/search/weapons'
import AwardKillsModal from '../features/awards/AwardKillsModal'
import { t } from '../lib/i18n'

const WEAPON_LABEL = Object.fromEntries(WEAPONS.map((w) => [w.value, w.label]))

// Leaderboards page: one card per superlative, ranking players across your matches. Scope
// with the team (roster) and map filters. Pages stay thin — data comes from useAwards.
export default function AwardsPage() {
  const [teamId, setTeamId] = useState('')
  const [map, setMap] = useState('')
  const { teams } = useTeams()
  const { matches } = useMatches()
  const { awards, loading, error } = useAwards(teamId, map)
  // The leaderboard row whose kills are open, as { award, player }.
  const [drill, setDrill] = useState(null)

  const maps = useMemo(
    () => [...new Set(matches.map((m) => m.map_name).filter(Boolean))].sort(),
    [matches],
  )

  return (
    <section className="awards">
      <div className="awards-head">
        <h2>Awards</h2>
        <div className="awards-filters">
          {teams.length > 0 && (
            <select value={teamId} onChange={(e) => setTeamId(e.target.value)}>
              <option value="">everyone</option>
              {teams.map((tm) => <option key={tm.id} value={tm.id}>{tm.name}</option>)}
            </select>
          )}
          <select value={map} onChange={(e) => setMap(e.target.value)}>
            <option value="">all maps</option>
            {maps.map((m) => <option key={m} value={m}>{m}</option>)}
          </select>
        </div>
      </div>

      {error && <p className="error">{t(error)}</p>}
      {loading && awards.length === 0 && <p className="muted">Tallying…</p>}
      {!loading && awards.length === 0 && !error && (
        <p className="muted">No awards yet — upload and parse some matches.</p>
      )}

      <div className="awards-grid">
        {awards.map((a) => (
          <article key={a.key} className="award-card">
            <header className="award-card-head">
              <span className="award-emoji" aria-hidden="true">{a.emoji}</span>
              <div>
                <h3>{a.label}</h3>
                <p className="award-hint muted">{a.hint}</p>
              </div>
            </header>
            <ol className="award-leaders">
              {a.leaders.map((l, i) => (
                <li key={i} className={i === 0 ? 'lead' : ''}>
                  <button type="button" className="award-leader" onClick={() => setDrill({ award: a, player: l })}>
                    <span className="award-rank">{i + 1}</span>
                    <span className="award-name">{l.name}</span>
                    <span className="award-value">
                      {l.value}
                      {l.sub && (
                        <span className="award-sub"> {a.key === 'one_trick' ? (WEAPON_LABEL[l.sub] || l.sub) : l.sub}</span>
                      )}
                    </span>
                  </button>
                </li>
              ))}
            </ol>
          </article>
        ))}
      </div>

      {drill && (
        <AwardKillsModal award={drill.award} player={drill.player} map={map} onClose={() => setDrill(null)} />
      )}
    </section>
  )
}
