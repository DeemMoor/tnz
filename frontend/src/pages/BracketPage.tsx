import { useCallback, useEffect, useState, type CSSProperties, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import type { AvailablePlayer, Bracket, BracketMatch, BracketPlayer, BracketRound } from '../types'
import { useAuth } from '../auth/AuthContext'
import {
  getBracket,
  markWinner,
  clearMatch,
  getAvailablePlayers,
  fillBye,
  fillByeWalkIn,
  replacePlayer,
  replacePlayerWalkIn,
  removePlayer,
} from '../api/bracket'

// Колонка двусторонней сетки: сторона, «шаг» слота, флаги краёв и матчи.
type Column = {
  key: string
  side: 'left' | 'right' | 'center'
  round: number // для расчёта --slot (1-based от края колонки)
  matchRound: number // настоящий номер тура матчей (1 = первый сыгранный)
  firstCol: boolean // крайняя колонка (нет входящей линии)
  preFinal: boolean // полуфинал: к финалу идёт простой горизонталью
  label: string
  matches: BracketMatch[]
}

// Кого тапнули: игрок + матч, в котором он стоит (с номером стола и тура).
type MenuTarget = {
  match: BracketMatch
  player: { id: number; name: string }
  tableNumber: number
  round: number
}

// Выбор игрока: сажаем в пустой слот (outgoing = null) или меняем кого-то.
type Picker = {
  match: BracketMatch
  outgoing: { id: number; name: string } | null
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
      matchRound: 1,
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
      matchRound: r,
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
    matchRound: R,
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
      matchRound: r,
      firstCol: r === 1,
      preFinal: r === R - 1,
      label: rd.label,
      matches: rd.matches.slice(half),
    })
  }
  return cols
}

// BracketPage — публичная турнирная сетка. Тап по игроку открывает меню:
// отметить победителя, заменить, убрать из сетки, посмотреть профиль.
export default function BracketPage() {
  const { id } = useParams()
  const tournamentId = Number(id)
  const { user } = useAuth()
  const navigate = useNavigate()

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
  const finished = bracket?.tournament.status === 'finished'

  // Меню по тапу на игроке и шаг подтверждения удаления внутри него.
  const [menu, setMenu] = useState<MenuTarget | null>(null)
  const [confirmRemove, setConfirmRemove] = useState(false)

  // Пикер: кого посадить в свободный слот / кем заменить игрока.
  const [picker, setPicker] = useState<Picker | null>(null)
  const [pickerPlayers, setPickerPlayers] = useState<AvailablePlayer[] | null>(null)
  const [pickerError, setPickerError] = useState<string | null>(null)
  const [newPlayerMode, setNewPlayerMode] = useState(false)
  const [phone, setPhone] = useState('')
  const [name, setName] = useState('')

  function closeMenu() {
    setMenu(null)
    setConfirmRemove(false)
  }

  // Выполнить действие и перечитать сетку (после отметки победителя состав
  // следующих туров меняется, поэтому всегда тянем свежие данные).
  async function run(action: () => Promise<{ ok: boolean; error?: string }>) {
    setBusy(true)
    setError(null)
    const res = await action()
    if (!res.ok) setError(res.error ?? 'Ошибка')
    closeMenu()
    load()
    setBusy(false)
  }

  async function openPicker(outgoing: { id: number; name: string } | null) {
    if (!menu) return
    setPicker({ match: menu.match, outgoing })
    setPickerPlayers(null)
    setPickerError(null)
    setNewPlayerMode(false)
    setPhone('')
    setName('')
    closeMenu()
    try {
      setPickerPlayers(await getAvailablePlayers(tournamentId))
    } catch {
      setPickerError('Не удалось загрузить список игроков')
    }
  }

  // Пустой слот в первом туре — админ сажает туда игрока (кнопка «+ добавить»).
  async function openPickerForEmptySlot(m: BracketMatch) {
    setPicker({ match: m, outgoing: null })
    setPickerPlayers(null)
    setPickerError(null)
    setNewPlayerMode(false)
    setPhone('')
    setName('')
    try {
      setPickerPlayers(await getAvailablePlayers(tournamentId))
    } catch {
      setPickerError('Не удалось загрузить список игроков')
    }
  }

  function closePicker() {
    setPicker(null)
    setPickerPlayers(null)
    setPickerError(null)
    setNewPlayerMode(false)
    setPhone('')
    setName('')
  }

  async function pickExisting(playerId: number) {
    if (!picker) return
    setBusy(true)
    setError(null)
    const { match, outgoing } = picker
    const res = outgoing
      ? await replacePlayer(tournamentId, match.id, outgoing.id, playerId)
      : await fillBye(tournamentId, match.id, playerId)
    if (!res.ok) setError(res.error ?? 'Ошибка')
    closePicker()
    load()
    setBusy(false)
  }

  async function submitNewPlayer(e: FormEvent) {
    e.preventDefault()
    if (!picker) return
    setBusy(true)
    setPickerError(null)
    const { match, outgoing } = picker
    const res = outgoing
      ? await replacePlayerWalkIn(tournamentId, match.id, outgoing.id, phone, name)
      : await fillByeWalkIn(tournamentId, match.id, phone, name)
    if (!res.ok) {
      setPickerError(res.error ?? 'Ошибка')
      setBusy(false)
      return
    }
    closePicker()
    load()
    setBusy(false)
  }

  // Кто может отметить результат: админ (в т.ч. переотметить уже сыгранный —
  // на случай ошибочного тапа) или один из двух игроков, пока матч не сыгран.
  function canScore(m: BracketMatch): boolean {
    if (!m.player1 || !m.player2) return false
    if (isAdmin) return true
    if (m.status !== 'pending') return false
    return user != null && (user.id === m.player1.id || user.id === m.player2.id)
  }

  function formatDate(ymd: string): string {
    return new Date(ymd + 'T00:00:00').toLocaleDateString('ru-RU', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    })
  }

  // Одна строка игрока в карточке матча.
  function PlayerRow({
    m,
    player,
    tableNumber,
    round,
  }: {
    m: BracketMatch
    player: BracketPlayer
    tableNumber: number
    round: number
  }) {
    const isWinner = player != null && m.winnerId === player.id
    const empty = player == null
    // Пустой слот 1-го тура — админ может посадить сюда игрока.
    const fillable = isAdmin && empty && round === 1 && !finished
    const label = player
      ? player.name
      : fillable
        ? '+ добавить игрока'
        : m.status === 'done'
          ? 'нет соперника'
          : 'ждём соперника'
    const cls = [
      'prow',
      isWinner ? 'won' : '',
      !empty || fillable ? 'pickable' : '',
      fillable ? 'fillable' : '',
      empty && !fillable ? 'empty' : '',
    ]
      .join(' ')
      .trim()

    if (player) {
      return (
        <button
          type="button"
          className={cls}
          disabled={busy}
          onClick={() => {
            setConfirmRemove(false)
            setMenu({ match: m, player, tableNumber, round })
          }}
        >
          {label}
        </button>
      )
    }
    if (fillable) {
      return (
        <button type="button" className={cls} disabled={busy} onClick={() => openPickerForEmptySlot(m)}>
          {label}
        </button>
      )
    }
    return <div className={cls}>{label}</div>
  }

  // Меню действий по игроку. Набор пунктов зависит от того, кто смотрит
  // (админ / участник матча / гость) и что уже произошло в матче.
  function PlayerMenu({ target }: { target: MenuTarget }) {
    const { match: m, player, round } = target
    const isWinner = m.winnerId === player.id
    const bothPlayers = m.player1 != null && m.player2 != null
    const scorable = canScore(m)
    const canEdit = isAdmin && round === 1 && !finished

    return (
      <div className="player-menu-backdrop" onClick={closeMenu}>
        <div className="player-menu" onClick={(e) => e.stopPropagation()}>
          <div className="player-menu-head">{player.name}</div>

          {confirmRemove ? (
            <>
              <p className="player-menu-note">
                Убрать игрока из сетки? Результат матча, если он был, отменится.
              </p>
              <button
                type="button"
                className="danger"
                disabled={busy}
                onClick={() => run(() => removePlayer(tournamentId, m.id, player.id))}
              >
                Да, убрать
              </button>
              <button type="button" className="secondary" onClick={() => setConfirmRemove(false)}>
                Назад
              </button>
            </>
          ) : (
            <>
              {scorable && bothPlayers && !isWinner && (
                <button
                  type="button"
                  disabled={busy}
                  onClick={() => run(() => markWinner(m.id, player.id, false))}
                >
                  {m.status === 'done' ? 'Сделать победителем' : 'Победитель'}
                </button>
              )}

              {scorable && bothPlayers && m.status === 'pending' && (
                <button
                  type="button"
                  disabled={busy}
                  onClick={() => run(() => markWinner(m.id, player.id, true))}
                >
                  Проходит без игры (соперник не явился)
                </button>
              )}

              {isAdmin && !bothPlayers && m.status === 'pending' && (
                <button
                  type="button"
                  disabled={busy}
                  onClick={() => run(() => markWinner(m.id, player.id, false))}
                >
                  Проходит дальше (нет соперника)
                </button>
              )}

              {isAdmin && isWinner && m.status === 'done' && (
                <button type="button" disabled={busy} onClick={() => run(() => clearMatch(m.id))}>
                  Отменить результат
                </button>
              )}

              {canEdit && !(isWinner && m.status === 'done') && (
                <button type="button" disabled={busy} onClick={() => openPicker(player)}>
                  Заменить
                </button>
              )}

              {canEdit && (
                <button type="button" className="danger" disabled={busy} onClick={() => setConfirmRemove(true)}>
                  Очистить
                </button>
              )}

              <button type="button" onClick={() => navigate(`/players/${player.id}`)}>
                Профиль
              </button>

              <button type="button" className="secondary" onClick={closeMenu}>
                Отмена
              </button>
            </>
          )}
        </div>
      </div>
    )
  }

  return (
    <main className="page bracket-page">
      {error && <div className="form-error">{error}</div>}
      {!bracket && !error && <p>Загрузка…</p>}

      {bracket && (
        <>
          <h1>
            Турнир #{bracket.tournament.number}
            {finished && ' · завершён'}
          </h1>
          <p className="muted">{formatDate(bracket.tournament.date)}</p>
          {user == null && (
            <p className="hint">Войдите, чтобы отмечать результаты своих матчей.</p>
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
                          <PlayerRow m={m} player={m.player1} tableNumber={table.tableNumber} round={col.matchRound} />
                          <PlayerRow m={m} player={m.player2} tableNumber={table.tableNumber} round={col.matchRound} />
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

      {menu && <PlayerMenu target={menu} />}

      {picker && (
        <div className="bye-picker-backdrop" onClick={closePicker}>
          <div className="bye-picker" onClick={(e) => e.stopPropagation()}>
            <h3>{picker.outgoing ? `Кем заменить: ${picker.outgoing.name}` : 'Кого посадить?'}</h3>
            <p className="muted">
              {picker.outgoing
                ? 'Сыгранная игра засчитается победителю, матч начнётся заново с новым соперником.'
                : 'Игрок займёт этот пустой слот и сыграет реальный матч.'}
            </p>
            {pickerError && <div className="form-error">{pickerError}</div>}

            {!newPlayerMode && (
              <>
                {pickerPlayers === null && !pickerError && <p>Загрузка…</p>}
                {pickerPlayers?.length === 0 && (
                  <p className="muted">Нет выбывших игроков — заведите нового.</p>
                )}
                <ul className="bye-picker-list">
                  {pickerPlayers?.map((p) => (
                    <li key={p.id}>
                      <button type="button" disabled={busy} onClick={() => pickExisting(p.id)}>
                        {p.name}
                      </button>
                    </li>
                  ))}
                </ul>
                <button
                  type="button"
                  className="secondary"
                  disabled={busy}
                  onClick={() => {
                    setNewPlayerMode(true)
                    setPickerError(null)
                  }}
                >
                  + Другой игрок (пришёл только что, в т.ч. незарегистрированный)
                </button>
              </>
            )}

            {newPlayerMode && (
              <form className="form" onSubmit={submitNewPlayer}>
                <label>
                  Телефон
                  <input
                    type="tel"
                    inputMode="tel"
                    autoComplete="tel"
                    placeholder="+7 900 000-00-00"
                    value={phone}
                    onChange={(e) => setPhone(e.target.value)}
                    required
                  />
                </label>
                <label>
                  Фамилия и имя (только для нового игрока)
                  <input type="text" value={name} onChange={(e) => setName(e.target.value)} />
                </label>
                <span className="hint">Если игрок уже зарегистрирован в системе — впишите только телефон.</span>
                <button type="submit" disabled={busy}>
                  {picker.outgoing ? 'Заменить' : 'Посадить'}
                </button>
                <button
                  type="button"
                  className="secondary"
                  disabled={busy}
                  onClick={() => {
                    setNewPlayerMode(false)
                    setPickerError(null)
                  }}
                >
                  Назад к списку
                </button>
              </form>
            )}

            <button type="button" className="secondary" onClick={closePicker}>
              Отмена
            </button>
          </div>
        </div>
      )}
    </main>
  )
}
