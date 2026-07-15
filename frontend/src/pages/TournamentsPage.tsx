import { useEffect, useState } from 'react'
import type { Tournament } from '../types'
import { listTournaments } from '../api/tournaments'
import TournamentCard from '../components/TournamentCard'

// TournamentsPage — список всех турниров (ближайшие сверху).
export default function TournamentsPage() {
  const [tournaments, setTournaments] = useState<Tournament[] | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    listTournaments()
      .then(setTournaments)
      .catch(() => setError('Не удалось загрузить турниры'))
  }, [])

  return (
    <main className="page">
      <h1>Турниры</h1>

      {error && <div className="form-error">{error}</div>}
      {!tournaments && !error && <p>Загрузка…</p>}
      {tournaments && tournaments.length === 0 && (
        <p className="muted">Турниров пока нет.</p>
      )}

      <div className="tournaments-grid">
        {tournaments?.map((t) => (
          <TournamentCard key={t.id} tournament={t} />
        ))}
      </div>
    </main>
  )
}
