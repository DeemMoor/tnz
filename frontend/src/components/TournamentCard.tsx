import { useState } from 'react'
import { Link } from 'react-router-dom'
import type { Tournament } from '../types'
import { useAuth } from '../auth/AuthContext'
import {
  registerForTournament,
  unregisterFromTournament,
  checkInSelf,
} from '../api/tournaments'

// Форматирование даты турнира: «воскресенье, 19 июля 2026 г.».
function formatDate(ymd: string): string {
  return new Date(ymd + 'T00:00:00').toLocaleDateString('ru-RU', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
}

function formatDateTime(iso: string): string {
  return new Date(iso).toLocaleString('ru-RU', {
    weekday: 'short',
    day: 'numeric',
    month: 'long',
    hour: '2-digit',
    minute: '2-digit',
  })
}

// Понятная подпись стадии турнира.
const STATUS_LABEL: Record<string, string> = {
  draft: 'черновик',
  registration: 'идёт запись',
  checkin: 'чекин',
  drawn: 'жеребьёвка проведена',
  in_progress: 'идут игры',
  finished: 'завершён',
}

// TournamentCard — одна карточка турнира со статусом и действием.
export default function TournamentCard({ tournament }: { tournament: Tournament }) {
  const { user } = useAuth()
  const [t, setT] = useState(tournament)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const me = t.me
  const isActive = me?.status === 'registered' || me?.status === 'waitlisted'
  const isAdmin = user?.roles.includes('ROLE_ADMIN') ?? false

  // После жеребьёвки есть сетка; до неё — фаза записи/чекина.
  const hasBracket = ['drawn', 'in_progress', 'finished'].includes(t.status)
  const preDraw = ['draft', 'registration', 'checkin'].includes(t.status)
  const canCheckIn = me?.status === 'registered' && !me.checkedIn && t.checkinOpen

  async function onRegister() {
    setBusy(true)
    setError(null)
    const res = await registerForTournament(t.id)
    if (res.ok && res.tournament) setT(res.tournament)
    else setError(res.error ?? 'Ошибка')
    setBusy(false)
  }

  async function onUnregister() {
    setBusy(true)
    setError(null)
    const res = await unregisterFromTournament(t.id)
    if (res.ok && res.tournament) setT(res.tournament)
    else setError(res.error ?? 'Ошибка')
    setBusy(false)
  }

  async function onCheckin() {
    setBusy(true)
    setError(null)
    const res = await checkInSelf(t.id)
    if (res.ok && res.tournament) setT(res.tournament)
    else setError(res.error ?? 'Ошибка')
    setBusy(false)
  }

  return (
    <div className="card">
      <div className="card-head">
        <h2>Турнир #{t.number}</h2>
        <span className="date">{formatDate(t.date)}</span>
        <span className={`status-pill status-${t.status}`}>
          {STATUS_LABEL[t.status] ?? t.status}
        </span>
      </div>

      <div className="counts">
        Участники: {t.registeredCount}/{t.capacity}
        {t.waitlistCount > 0 && <> · очередь: {t.waitlistCount}</>}
      </div>

      {/* Мой статус — только в фазе записи (до жеребьёвки). */}
      {preDraw && me?.status === 'registered' && (
        <div className="badge badge-ok">
          {me.checkedIn ? 'Вы участвуете (отмечены)' : 'Вы записаны'}
        </div>
      )}
      {preDraw && me?.status === 'waitlisted' && (
        <div className="badge badge-wait">
          В очереди{me.waitlistPosition ? ` · №${me.waitlistPosition}` : ''}
        </div>
      )}

      {canCheckIn && (
        <button type="button" onClick={onCheckin} disabled={busy}>
          Я на месте — отметиться
        </button>
      )}

      {error && <div className="form-error">{error}</div>}

      <div className="card-actions">
        {/* Сетка — если жеребьёвка проведена. */}
        {hasBracket && (
          <Link className="btn" to={`/tournaments/${t.id}/bracket`}>
            Смотреть сетку
          </Link>
        )}

        {/* Запись/снятие — только до жеребьёвки. */}
        {preDraw && !user && (
          <Link className="btn btn-outline" to="/login">
            Войдите, чтобы участвовать
          </Link>
        )}
        {preDraw && user && isActive && (
          <button type="button" className="secondary" onClick={onUnregister} disabled={busy}>
            Снять с регистрации
          </button>
        )}
        {preDraw && user && !isActive && t.registrationOpen && (
          <button type="button" onClick={onRegister} disabled={busy}>
            Участвовать
          </button>
        )}
        {preDraw && user && !isActive && !t.registrationOpen && (
          <span className="hint">
            Регистрация откроется {formatDateTime(t.registrationOpensAt)}
          </span>
        )}

        {/* Экран чекина админу — только в фазе записи/чекина. */}
        {isAdmin && preDraw && (
          <Link className="btn-link" to={`/checkin/${t.id}`}>
            Экран чекина (админ)
          </Link>
        )}
      </div>
    </div>
  )
}
