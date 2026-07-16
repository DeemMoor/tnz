import { useEffect, useState } from 'react'
import type { PlayerStat } from '../types'
import { getStats } from '../api/stats'
import Avatar from '../components/Avatar'

// StatsPage — публичная таблица игроков: игры, победы, поражения, очки.
export default function StatsPage() {
  const [players, setPlayers] = useState<PlayerStat[] | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    getStats()
      .then(setPlayers)
      .catch(() => setError('Не удалось загрузить статистику'))
  }, [])

  return (
    <main className="page">
      <h1>Статистика</h1>
      <p className="muted">Очки = число побед в матчах.</p>

      {error && <div className="form-error">{error}</div>}
      {!players && !error && <p>Загрузка…</p>}
      {players && players.length === 0 && (
        <p className="muted">Пока нет сыгранных матчей.</p>
      )}

      {players && players.length > 0 && (
        <table className="stats">
          <thead>
            <tr>
              <th>#</th>
              <th>Игрок</th>
              <th title="Игры">И</th>
              <th title="Победы">В</th>
              <th title="Поражения">П</th>
              <th title="Очки">Очки</th>
            </tr>
          </thead>
          <tbody>
            {players.map((p, i) => (
              <tr key={p.userId}>
                <td className="rank">{i + 1}</td>
                <td className="pname">
                  <span className="pname-cell">
                    <Avatar name={p.name} url={p.avatarUrl} size={28} />
                    {p.name}
                  </span>
                </td>
                <td>{p.games}</td>
                <td>{p.wins}</td>
                <td>{p.losses}</td>
                <td className="pts">{p.points}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </main>
  )
}
