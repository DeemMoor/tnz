import { useEffect, useState, type FormEvent } from 'react'
import { Link, Navigate, useParams } from 'react-router-dom'
import type { Roster } from '../types'
import { useAuth } from '../auth/AuthContext'
import { getRoster, adminCheckIn, adminUncheckIn, walkIn, closeCheckin, drawTournament } from '../api/checkin'

// CheckinPage — админский экран дня турнира: отметки, walk-in, закрытие чекина
// и жеребьёвка. После жеребьёвки состав фиксируется, показываем «Открыть сетку».
export default function CheckinPage() {
  const { user, loading } = useAuth()
  const { id } = useParams()
  const tournamentId = Number(id)

  const [roster, setRoster] = useState<Roster | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [wiPhone, setWiPhone] = useState('')
  const [wiName, setWiName] = useState('')
  const [wiError, setWiError] = useState<string | null>(null)

  useEffect(() => {
    if (!Number.isNaN(tournamentId)) {
      getRoster(tournamentId)
        .then(setRoster)
        .catch(() => setError('Не удалось загрузить ростер'))
    }
  }, [tournamentId])

  if (loading) return <main className="page">Загрузка…</main>
  if (!user) return <Navigate to="/login" replace />
  if (!user.roles.includes('ROLE_ADMIN')) return <Navigate to="/" replace />

  // После жеребьёвки состав менять нельзя — только смотреть сетку.
  const drawn = roster != null && ['drawn', 'in_progress', 'finished'].includes(roster.status)
  const checkinClosed = roster?.status === 'checkin'

  async function refresh() {
    try {
      setRoster(await getRoster(tournamentId))
    } catch {
      /* оставляем прежнее состояние */
    }
  }

  async function onCheck(userId: number) {
    setBusy(true)
    setError(null)
    try {
      setRoster(await adminCheckIn(tournamentId, userId))
    } catch {
      setError('Ошибка отметки')
    } finally {
      setBusy(false)
    }
  }

  async function onUncheck(userId: number) {
    if (!confirm('Снять отметку о приходе? Игрок останется записанным, но при закрытии чекина уйдёт из состава.')) return
    setBusy(true)
    setError(null)
    try {
      setRoster(await adminUncheckIn(tournamentId, userId))
    } catch {
      setError('Ошибка снятия отметки')
    } finally {
      setBusy(false)
    }
  }

  async function onWalkIn(e: FormEvent) {
    e.preventDefault()
    setWiError(null)
    setBusy(true)
    const res = await walkIn(tournamentId, wiPhone, wiName)
    if (res.ok && res.roster) {
      setRoster(res.roster)
      setWiPhone('')
      setWiName('')
    } else {
      setWiError(res.error ?? 'Ошибка')
    }
    setBusy(false)
  }

  async function onClose() {
    if (!confirm('Закрыть чекин? Незачекиненные будут сброшены, места добьются из очереди.')) return
    setBusy(true)
    setError(null)
    try {
      setRoster(await closeCheckin(tournamentId))
    } catch {
      setError('Ошибка закрытия чекина')
    } finally {
      setBusy(false)
    }
  }

  async function onDraw() {
    if (!confirm('Провести жеребьёвку? Изменить состав после этого нельзя.')) return
    setBusy(true)
    setError(null)
    const res = await drawTournament(tournamentId)
    if (!res.ok) setError(res.error ?? 'Ошибка жеребьёвки')
    await refresh() // всегда синхронизируем состояние (в т.ч. если уже проведена)
    setBusy(false)
  }

  return (
    <main className="page checkin-page">
      <h1>Чекин</h1>

      {error && <div className="form-error">{error}</div>}
      {!roster && !error && <p>Загрузка…</p>}

      {roster && (
        <>
          <div className="counts">
            Отмечено: <strong>{roster.checkedInCount}</strong> / {roster.registeredCount}
            {' · '}очередь: {roster.waitlistCount}
          </div>

          {/* Жеребьёвка проведена — состав зафиксирован. */}
          {drawn && (
            <div className="card draw-done">
              <div className="badge badge-ok">Жеребьёвка проведена</div>
              <p className="muted">Состав зафиксирован: {roster.registeredCount} игроков.</p>
              <Link className="btn btn-lg" to={`/tournaments/${tournamentId}/bracket`}>
                Открыть сетку →
              </Link>
            </div>
          )}

          {/* До жеребьёвки — добавление игрока в турнир (нового или уже заведённого). */}
          {!drawn && (
            <form className="form walkin card" onSubmit={onWalkIn}>
              <strong>Добавить участника</strong>
              <input type="tel" placeholder="Телефон" value={wiPhone} onChange={(e) => setWiPhone(e.target.value)} required />
              <input type="text" placeholder="Фамилия и Имя (только для нового игрока)" value={wiName} onChange={(e) => setWiName(e.target.value)} />
              <span className="hint">
                Если игрок уже зарегистрирован — впиши только телефон. В день турнира
                (окно чекина) добавленный сразу отмечается как пришедший.
              </span>
              {wiError && <div className="form-error">{wiError}</div>}
              <button type="submit" disabled={busy}>
                Добавить в турнир
              </button>
            </form>
          )}

          <h2>Участники</h2>
          <ul className="roster">
            {roster.main.map((e) => (
              <li key={e.userId} className={e.checkedIn ? 'checked' : ''}>
                <span className="rname">{e.name}</span>
                <span className="rphone">{e.phone}</span>
                {e.checkedIn ? (
                  !drawn ? (
                    <span className="check-actions">
                      <span className="ok-check">✓</span>
                      <button type="button" className="small" onClick={() => onUncheck(e.userId)} disabled={busy}>
                        Снять
                      </button>
                    </span>
                  ) : (
                    <span className="ok-check">✓</span>
                  )
                ) : !drawn ? (
                  <button type="button" className="small" onClick={() => onCheck(e.userId)} disabled={busy}>
                    Отметить
                  </button>
                ) : (
                  <span className="muted">—</span>
                )}
              </li>
            ))}
          </ul>

          {roster.waitlist.length > 0 && (
            <>
              <h2>Очередь ожидания</h2>
              <ol className="roster">
                {roster.waitlist.map((e) => (
                  <li key={e.userId}>
                    <span className="rname">{e.name}</span>
                    <span className="rphone">{e.phone}</span>
                  </li>
                ))}
              </ol>
            </>
          )}

          {/* Главное действие стадии (скрыто после жеребьёвки). */}
          {!drawn && (
            <div className="stage-action">
              {checkinClosed ? (
                <button type="button" className="btn-lg" onClick={onDraw} disabled={busy}>
                  Провести жеребьёвку
                </button>
              ) : (
                <button type="button" className="btn-lg danger" onClick={onClose} disabled={busy}>
                  Закрыть чекин (14:15)
                </button>
              )}
            </div>
          )}
        </>
      )}
    </main>
  )
}
