import { useEffect, useState } from 'react'
import { getChampions, type ChampionTournament } from '../api/champions'
import Avatar from '../components/Avatar'

function formatDate(ymd: string): string {
  return new Date(ymd + 'T00:00:00').toLocaleDateString('ru-RU', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
}

// ChampionsPage — публичная страница чемпионов (за всё время).
export default function ChampionsPage() {
  const [list, setList] = useState<ChampionTournament[] | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    getChampions()
      .then(setList)
      .catch(() => setError('Не удалось загрузить чемпионов'))
  }, [])

  return (
    <main className="page">
      <h1>Чемпионы</h1>
      <p className="muted">Победители каждого турнира — за всё время.</p>

      {error && <div className="form-error">{error}</div>}
      {!list && !error && <p>Загрузка…</p>}
      {list && list.length === 0 && (
        <p className="muted">Пока нет завершённых турниров.</p>
      )}

      <div className="stack">
        {list?.map((t) => (
          <div key={t.number} className="card champ-card">
            <div className="champ-head">
              <span className="champ-title">Турнир #{t.number}</span>
              <span className="champ-date">{formatDate(t.date)}</span>
            </div>
            <div className="champ-list">
              {t.champions.map((c) => (
                <div key={c.tableNumber} className="champ-item">
                  <span className="champ-cup">🏆</span>
                  <Avatar name={c.name} url={c.avatarUrl} size={36} />
                  <span className="champ-name">{c.name}</span>
                  <span className="champ-table">стол {c.tableNumber}</span>
                </div>
              ))}
            </div>
          </div>
        ))}
      </div>
    </main>
  )
}
