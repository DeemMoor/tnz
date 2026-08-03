import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import type { PlayerCard } from '../types'
import { getPlayer } from '../api/players'
import Avatar from '../components/Avatar'

// PlayerCardPage — публичная карточка игрока: аватар, имя и рейтинг по
// внутренним турнирам. Открывается из сетки (меню → «Профиль») и из статистики.
export default function PlayerCardPage() {
  const { id } = useParams()
  const playerId = Number(id)

  const [player, setPlayer] = useState<PlayerCard | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (Number.isNaN(playerId)) return
    getPlayer(playerId)
      .then(setPlayer)
      .catch(() => setError('Игрок не найден'))
  }, [playerId])

  return (
    <main className="page player-card-page">
      {error && <div className="form-error">{error}</div>}
      {!player && !error && <p>Загрузка…</p>}

      {player && (
        <>
          <div className="player-card-head">
            <Avatar name={player.name} url={player.avatarUrl} size={96} />
            <div>
              <h1>{player.name}</h1>
              {player.isChampion && <p className="player-card-champion">Чемпион стола</p>}
              {player.stats.rank !== null && (
                <p className="muted">{player.stats.rank}-е место в общей таблице</p>
              )}
            </div>
          </div>

          <div className="player-card-stats">
            <div className="pstat">
              <span className="pstat-value">{player.stats.points}</span>
              <span className="pstat-label">очки</span>
            </div>
            <div className="pstat">
              <span className="pstat-value">{player.stats.games}</span>
              <span className="pstat-label">игры</span>
            </div>
            <div className="pstat">
              <span className="pstat-value">{player.stats.wins}</span>
              <span className="pstat-label">победы</span>
            </div>
            <div className="pstat">
              <span className="pstat-value">{player.stats.losses}</span>
              <span className="pstat-label">поражения</span>
            </div>
          </div>

          {player.stats.games === 0 && (
            <p className="muted">Пока нет сыгранных матчей.</p>
          )}
          <p className="hint">Рейтинг считается по нашим турнирам: очки = число побед в матчах.</p>
        </>
      )}
    </main>
  )
}
