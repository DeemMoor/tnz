import { useCallback, useEffect, useState, type CSSProperties } from 'react'
import { useParams } from 'react-router-dom'
import type { Bracket, BracketMatch, BracketPlayer, BracketRound } from '../types'
import { useAuth } from '../auth/AuthContext'
import { getBracket, markWinner } from '../api/bracket'

// Колонка двусторонней сетки: сторона, «шаг» слота, флаги краёв и матчи.
type Column = {
  key: string
  side: 'left' | 'right' | 'center'
  round: number // для расчёта --slot (1-based от края)
  firstCol: boolean // крайняя колонка (нет входящей линии)
  preFinal: boolean // полуфинал: к финалу идёт простой горизонталью
  label: string
  matches: BracketMatch[]
}

// Раскладываем туры в двусторонний bracket: половина пар слева, половина справа,
// оба крыла сходятся к финалу в центре (как рисуют турнирную сетку на бумаге).
function buildColumns(rounds: BracketRound[]): Column[] {
  const R = rounds.length
  if (R <= 1) {
    return rounds.map((rd) => ({
      key: 'c',
      side: 'center' as const,
      round: 1,
      firstCol: true,
      preFinal: false,
      label: rd.label,
      matches: rd.matches,
    }))
  }

  const cols: Column[] = []
  // Левое крыло: туры 1..R-1, берём первую половину матчей каждого тура.
  for (let r = 1; r <= R - 1; r++) {
    const rd = rounds[r - 1]
    const half = rd.matches.length / 2
    cols.push({
      key: `l${r}`,
      side: 'left',
      round: r,
      firstCol: r === 1,
      preFinal: r === R - 1,
      label: rd.label,
      matches: rd.matches.slice(0, half),
    })
  }
  // Центр — финал.
  cols.push({
    key: 'c',
    side: 'center',
    round: R - 1,
    firstCol: false,
    preFinal: false,
    label: rounds[R - 1].label,
    matches: rounds[R - 1].matches,
  })
  // Правое крыло: туры R-1..1, вторая половина матчей, колонки от центра наружу.
  for (let r = R - 1; r >= 1; r--) {
    const rd = rounds[r - 1]
    const half = rd.matches.length / 2
    cols.push({
      key: `r${r}`,
      side: 'right',
      round: r,
      firstCol: r === 1,
      preFinal: r === R - 1,
      label: rd.label,
      matches: rd.matches.slice(half),
    })
  }
  return cols
}

// BracketPage — публичная турнирная сетка. Залогиненный участник (или админ)
// может тапнуть по победителю прямо в матче.
export default function BracketPage() {
  const { id } = useParams()
  const tournamentId = Number(id)
  const { user } = useAuth()

  const [bracket, setBracket] = useState<Bracket | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  const load = useCallback(() => {
    getBracket(tournamentId)
      .then(setBracket)
      .catch(() => setError('Не удалось загрузить сетку'))
  }, [tournamentId])

  useEffect(() => {
    if (!Number.isNaN(tournamentId)) load()
  }, [tournamentId, load])

  const isAdmin = user?.roles.includes('ROLE_ADMIN') ?? false

  // Режим неявки: тап по пришедшему = техпобеда (соперник не пришёл), без статы.
  const [walkoverMode, setWalkoverMode] = useState(false)

  // Кто может отметить этот матч: админ или один из двух игроков; матч готов и не сыгран.
  function canScore(m: BracketMatch): boolean {
    if (m.status !== 'pending' || !m.player1 || !m.player2) return false
    if (isAdmin) return true
    return user != null && (user.id === m.player1.id || user.id === m.player2.id)
  }

  async function onPick(m: BracketMatch, player: BracketPlayer) {
    if (!player || !canScore(m)) return
    if (walkoverMode && !confirm(`Засчитать неявку: ${player.name} проходит дальше без игры?`)) return
    setBusy(true)
    setError(null)
    const res = await markWinner(m.id, player.id, walkoverMode)
    if (!res.ok) setError(res.error ?? 'Ошибка')
    load() // перечитать сетку (продвижение победителя)
    setBusy(false)
  }

  function formatDate(ymd: string): string {
    return new Date(ymd + 'T00:00:00').toLocaleDateString('ru-RU', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    })
  }

  // Одна строка игрока в карточке матча.
  function PlayerRow({ m, player }: { m: BracketMatch; player: BracketPlayer }) {
    const isWinner = player != null && m.winnerId === player.id
    const clickable = canScore(m) && player != null
    const cls = ['prow', isWinner ? 'won' : '', clickable ? 'pickable' : ''].join(' ').trim()
    const label = player ? player.name : m.status === 'pending' ? '—' : 'bye'

    if (clickable) {
      return (
        <button type="button" className={cls} disabled={busy} onClick={() => onPick(m, player)}>
          {label}
        </button>
      )
    }
    return <div className={cls}>{label}</div>
  }

  return (
    <main className="page bracket-page">
      {error && <div className="form-error">{error}</div>}
      {!bracket && !error && <p>Загрузка…</p>}

      {bracket && (
        <>
          <h1>
            Турнир #{bracket.tournament.number}
            {bracket.tournament.status === 'finished' && ' · завершён'}
          </h1>
          <p className="muted">{formatDate(bracket.tournament.date)}</p>
          {user == null && (
            <p className="hint">Войдите, чтобы отмечать результаты своих матчей.</p>
          )}

          {/* Режим неявки: тап отмечает техпобеду (соперник не пришёл). */}
          {user != null && bracket.tournament.status !== 'finished' && (
            <label className={`walkover-toggle${walkoverMode ? ' on' : ''}`}>
              <input
                type="checkbox"
                checked={walkoverMode}
                onChange={(e) => setWalkoverMode(e.target.checked)}
              />
              Режим неявки: тапни того, кто пришёл — он проходит без игры (не в статистику)
            </label>
          )}

          {bracket.tables.length === 0 && (
            <p className="muted">Сетка появится после жеребьёвки.</p>
          )}

          {bracket.tables.map((table) => (
            <section key={table.tableNumber} className="table-bracket">
              <h2>Стол {table.tableNumber}</h2>
              {/* Двусторонний bracket; на узком экране скроллится вбок. */}
              <div className="rounds">
                {buildColumns(table.rounds).map((col) => (
                  <div
                    key={col.key}
                    className={[
                      'round-col',
                      `side-${col.side}`,
                      col.firstCol ? 'first-col' : '',
                      col.preFinal ? 'pre-final' : '',
                    ]
                      .join(' ')
                      .trim()}
                    style={{ '--slot': `${84 * 2 ** (col.round - 1)}px` } as CSSProperties}
                  >
                    <div className="round-label">{col.label}</div>
                    <div className="round-matches">
                      {col.matches.map((m) => (
                        <div key={m.id} className="match">
                          <PlayerRow m={m} player={m.player1} />
                          <PlayerRow m={m} player={m.player2} />
                        </div>
                      ))}
                    </div>
                  </div>
                ))}
              </div>
            </section>
          ))}
        </>
      )}
    </main>
  )
}
