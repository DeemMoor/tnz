import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import type { Participants } from '../types'
import { getParticipants } from '../api/tournaments'
import Avatar from '../components/Avatar'

// ParticipantsPage — публичный список участников турнира: записавшиеся и очередь.
export default function ParticipantsPage() {
  const { id } = useParams()
  const tournamentId = Number(id)
  const [data, setData] = useState<Participants | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!Number.isNaN(tournamentId)) {
      getParticipants(tournamentId)
        .then(setData)
        .catch(() => setError('Не удалось загрузить участников'))
    }
  }, [tournamentId])

  return (
    <main className="page">
      <div className="topbar">
        <Link to="/tournaments" className="back">
          ← К турнирам
        </Link>
      </div>

      {error && <div className="form-error">{error}</div>}
      {!data && !error && <p>Загрузка…</p>}

      {data && (
        <>
          <h1>Участники турнира #{data.number}</h1>
          <p className="muted">
            Записано: {data.registered.length}/{data.capacity}
            {data.waitlist.length > 0 && <> · очередь: {data.waitlist.length}</>}
          </p>

          {data.registered.length === 0 ? (
            <p className="muted">Пока никто не записался.</p>
          ) : (
            <ol className="players-list">
              {data.registered.map((p, i) => (
                <li key={i}>
                  <span className="pl-num">{i + 1}</span>
                  <Avatar name={p.name} url={p.avatarUrl} size={32} />
                  <span className="pl-name">{p.name}</span>
                  {p.checkedIn && <span className="pl-check">✓ на месте</span>}
                </li>
              ))}
            </ol>
          )}

          {data.waitlist.length > 0 && (
            <>
              <h2>Очередь ожидания</h2>
              <ol className="players-list">
                {data.waitlist.map((p, i) => (
                  <li key={i}>
                    <span className="pl-num">{i + 1}</span>
                    <Avatar name={p.name} url={p.avatarUrl} size={32} />
                    <span className="pl-name">{p.name}</span>
                  </li>
                ))}
              </ol>
            </>
          )}
        </>
      )}
    </main>
  )
}
