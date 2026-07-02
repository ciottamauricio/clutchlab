import { useState } from 'react'
import { useTactics, deleteTactic } from '../features/tactics/api'
import CreateTactic from '../features/tactics/CreateTactic'
import Board from '../features/tactics/Board'

export default function TacticsPage() {
  const { tactics, refresh } = useTactics()
  const [selectedId, setSelectedId] = useState(null)

  const onCreated = (tactic) => {
    refresh()
    setSelectedId(tactic.id)
  }

  const remove = async (id) => {
    await deleteTactic(id)
    if (selectedId === id) setSelectedId(null)
    refresh()
  }

  return (
    <>
      <CreateTactic onCreated={onCreated} />
      <div className="layout">
        <aside>
          <h2>Your tactics</h2>
          {tactics.length === 0 ? (
            <p className="muted">No tactics yet — create one above.</p>
          ) : (
            <ul className="tactic-list">
              {tactics.map((tactic) => (
                <li key={tactic.id} className={tactic.id === selectedId ? 'active' : ''}>
                  <button type="button" className="tactic-open" onClick={() => setSelectedId(tactic.id)}>
                    {tactic.name}
                  </button>
                  <button type="button" className="link-btn" onClick={() => remove(tactic.id)}>delete</button>
                </li>
              ))}
            </ul>
          )}
        </aside>
        <main>
          {selectedId ? (
            <Board key={selectedId} tacticId={selectedId} />
          ) : (
            <p className="muted">Select a tactic to open the board.</p>
          )}
        </main>
      </div>
    </>
  )
}
