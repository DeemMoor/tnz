import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'
import type { Tournament } from '../types'
import {
  getNearestTournament,
  registerForTournament,
  unregisterFromTournament,
  checkInSelf,
} from '../api/tournaments'

function formatDate(ymd: string): string {
  return new Date(ymd + 'T00:00:00').toLocaleDateString('ru-RU', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
}

function formatOpensAt(iso: string): string {
  return new Date(iso).toLocaleString('ru-RU', {
    weekday: 'short',
    day: 'numeric',
    month: 'long',
    hour: '2-digit',
    minute: '2-digit',
  })
}

// HomePage — главная. Ключевое действие — запись на ближайший турнир.
export default function HomePage() {
  const { user, loading } = useAuth()
  const [t, setT] = useState<Tournament | null | undefined>(undefined) // undefined=грузим
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  // Тянем ближайший турнир; перезапрашиваем при смене авторизации,
  // чтобы поле «me» (моя запись) было актуальным.
  useEffect(() => {
    getNearestTournament()
      .then(setT)
      .catch(() => setT(null))
  }, [user])

  async function onRegister() {
    if (!t) return
    setBusy(true)
    setError(null)
    const res = await registerForTournament(t.id)
    if (res.ok && res.tournament) setT(res.tournament)
    else setError(res.error ?? 'Не удалось записаться')
    setBusy(false)
  }

  async function onUnregister() {
    if (!t) return
    setBusy(true)
    setError(null)
    const res = await unregisterFromTournament(t.id)
    if (res.ok && res.tournament) setT(res.tournament)
    else setError(res.error ?? 'Ошибка')
    setBusy(false)
  }

  async function onCheckin() {
    if (!t) return
    setBusy(true)
    setError(null)
    const res = await checkInSelf(t.id)
    if (res.ok && res.tournament) setT(res.tournament)
    else setError(res.error ?? 'Не удалось отметиться')
    setBusy(false)
  }

  const me = t?.me
  const isActive = me?.status === 'registered' || me?.status === 'waitlisted'
  const hasBracket = t != null && ['drawn', 'in_progress', 'finished'].includes(t.status)

  // Главный блок действия по ближайшему турниру.
  function renderTournamentCTA() {
    if (t === undefined) return <p className="muted">Загрузка…</p>
    if (t === null) return <p className="muted">Ближайший турнир пока не назначен.</p>

    return (
      <div className="hero-tournament">
        <div className="ht-head">
          <span className="ht-title">Турнир #{t.number}</span>
          <span className="ht-date">{formatDate(t.date)}</span>
        </div>
        <div className="ht-counts">
          <Link to={`/tournaments/${t.id}/participants`} className="btn-link">
            Участники: {t.registeredCount}/{t.capacity}
          </Link>
          {t.waitlistCount > 0 && <> · очередь: {t.waitlistCount}</>}
        </div>

        {error && <div className="form-error">{error}</div>}

        {/* Не залогинен — сперва регистрация аккаунта. */}
        {!user && (
          <>
            <Link className="btn btn-lg" to="/register">
              Зарегистрироваться и участвовать
            </Link>
            <div className="hero-auth">
              Уже есть аккаунт? <Link to="/login">Войти</Link>
            </div>
          </>
        )}

        {/* Залогинен — действие зависит от статуса. */}
        {user && me?.status === 'registered' && (
          <>
            <div className="badge badge-ok">
              {me.checkedIn ? 'Вы участвуете (отмечены)' : 'Вы записаны в турнир'}
            </div>
            {/* В окно чекина (вс 14:00–14:15) — кнопка отметиться. */}
            {!me.checkedIn && t.checkinOpen && (
              <button type="button" className="btn-lg" onClick={onCheckin} disabled={busy}>
                Я на месте — отметиться
              </button>
            )}
            {!me.checkedIn && (
              <button type="button" className="secondary" onClick={onUnregister} disabled={busy}>
                Снять с регистрации
              </button>
            )}
          </>
        )}
        {user && me?.status === 'waitlisted' && (
          <>
            <div className="badge badge-wait">
              В очереди{me.waitlistPosition ? ` · №${me.waitlistPosition}` : ''}
            </div>
            <button type="button" className="secondary" onClick={onUnregister} disabled={busy}>
              Выйти из очереди
            </button>
          </>
        )}
        {user && !isActive && !hasBracket && t.registrationOpen && (
          <button type="button" className="btn-lg" onClick={onRegister} disabled={busy}>
            Участвовать в турнире #{t.number}
          </button>
        )}
        {user && !isActive && !hasBracket && !t.registrationOpen && (
          <div className="hero-auth">
            Регистрация откроется {formatOpensAt(t.registrationOpensAt)}
          </div>
        )}
        {hasBracket && (
          <Link className="btn btn-lg" to={`/tournaments/${t.id}/bracket`}>
            Смотреть сетку
          </Link>
        )}

        <div className="hero-auth">
          <Link to="/tournaments">Все турниры</Link> ·{' '}
          <Link to="/stats">статистика</Link>
        </div>
      </div>
    )
  }

  return (
    <main className="page">
      <section className="hero">
        <h1>Теннис на Новой Земле</h1>
        <p className="hero-sub">
          Регистрируйся, приходи на турнир, следи за сеткой и статистикой — с телефона
          или компьютера.
        </p>

        {!loading && renderTournamentCTA()}
      </section>

      <section className="features">
        <div className="feature">
          <div className="feature-title">Запись за 1 клик</div>
          <div className="feature-text">
            Регистрация по телефону. Первые 32 попадают в сетку, остальные — в
            очередь ожидания.
          </div>
        </div>
        <div className="feature">
          <div className="feature-title">Живая сетка</div>
          <div className="feature-text">
            Результаты матчей видны сразу — отмечай победителя прямо в сетке.
          </div>
        </div>
        <div className="feature">
          <div className="feature-title">Рейтинг игроков</div>
          <div className="feature-text">
            Победы копятся в общий зачёт. Смотри свою историю по турнирам.
          </div>
        </div>
      </section>
    </main>
  )
}
