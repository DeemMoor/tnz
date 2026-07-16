import { useEffect, useRef, useState, type FormEvent } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'
import { getMyTournaments } from '../api/tournaments'
import { uploadAvatar, deleteAvatar } from '../api/profile'
import Avatar from '../components/Avatar'
import type { MyTournamentStat } from '../types'

// ProfilePage — личный кабинет: правка имени/email, подтверждение email.
export default function ProfilePage() {
  const { user, refresh, logout } = useAuth()
  const navigate = useNavigate()
  // useSearchParams — читает query-параметры адреса (?verified=1 после письма).
  const [params] = useSearchParams()
  const verifiedParam = params.get('verified')

  const [name, setName] = useState(user?.name ?? '')
  const [nickname, setNickname] = useState(user?.nickname ?? '')
  const [telegram, setTelegram] = useState(user?.telegram ?? '')
  const [email, setEmail] = useState(user?.email ?? '')
  const fileRef = useRef<HTMLInputElement>(null)
  // Рейтинг RTTF: чекбокс «есть рейтинг» + число. null у пользователя = нет.
  const [hasRating, setHasRating] = useState(user?.rttfRating != null)
  const [rating, setRating] = useState(
    user?.rttfRating != null ? String(user.rttfRating) : '',
  )
  const [errors, setErrors] = useState<{
    name?: string
    email?: string
    rttfRating?: string
    nickname?: string
    telegram?: string
  }>({})
  const [notice, setNotice] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [history, setHistory] = useState<MyTournamentStat[] | null>(null)
  // Смена пароля — отдельная форма.
  const [curPass, setCurPass] = useState('')
  const [newPass, setNewPass] = useState('')
  const [pwMsg, setPwMsg] = useState<string | null>(null)
  const [pwErr, setPwErr] = useState<string | null>(null)

  // Подтягиваем историю выступлений один раз при открытии кабинета.
  useEffect(() => {
    getMyTournaments()
      .then(setHistory)
      .catch(() => setHistory([]))
  }, [])

  if (!user) return null // ProtectedRoute это гарантирует, но TS спокойнее

  function formatDate(ymd: string): string {
    return new Date(ymd + 'T00:00:00').toLocaleDateString('ru-RU', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    })
  }

  // Итоги по всем турнирам (для сводки сверху блока).
  const totalWins = history?.reduce((s, t) => s + t.wins, 0) ?? 0
  const totalGames = history?.reduce((s, t) => s + t.games, 0) ?? 0

  // «Сохранить» показываем только если данные реально изменились.
  const isDirty =
    name !== (user.name ?? '') ||
    nickname !== (user.nickname ?? '') ||
    telegram !== (user.telegram ?? '') ||
    (email || '') !== (user.email ?? '') ||
    hasRating !== (user.rttfRating != null) ||
    (hasRating && rating !== String(user.rttfRating ?? ''))

  async function save(e: FormEvent) {
    e.preventDefault()
    setErrors({})
    setNotice(null)
    setBusy(true)
    try {
      const res = await fetch('/api/me', {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({
          name,
          nickname: nickname || null,
          telegram: telegram || null,
          email: email || null,
          rttfRating: hasRating && rating !== '' ? Number(rating) : null,
        }),
      })
      if (res.ok) {
        await refresh()
        setNotice('Сохранено')
      } else if (res.status === 422) {
        const data = await res.json()
        setErrors(data.errors ?? {})
      } else {
        setNotice('Не удалось сохранить')
      }
    } finally {
      setBusy(false)
    }
  }

  async function resend() {
    setNotice(null)
    setBusy(true)
    try {
      const res = await fetch('/api/me/resend-verification', {
        method: 'POST',
        credentials: 'include',
      })
      setNotice(res.ok ? 'Письмо отправлено — проверьте почту' : 'Не получилось отправить')
    } finally {
      setBusy(false)
    }
  }

  async function onAvatarPick(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0]
    if (!file) return
    setNotice(null)
    setBusy(true)
    const res = await uploadAvatar(file)
    if (res.ok) {
      await refresh()
      setNotice('Аватар обновлён')
    } else {
      setNotice(res.error ?? 'Не удалось загрузить аватар')
    }
    setBusy(false)
    if (fileRef.current) fileRef.current.value = '' // сбросить выбор
  }

  async function onAvatarRemove() {
    setBusy(true)
    await deleteAvatar()
    await refresh()
    setBusy(false)
  }

  async function onLogout() {
    await logout()
    navigate('/')
  }

  async function changePassword(e: FormEvent) {
    e.preventDefault()
    setPwMsg(null)
    setPwErr(null)
    setBusy(true)
    try {
      const res = await fetch('/api/me/password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ currentPassword: curPass, newPassword: newPass }),
      })
      if (res.ok) {
        setPwMsg('Пароль изменён')
        setCurPass('')
        setNewPass('')
      } else {
        const data = await res.json().catch(() => ({}))
        setPwErr(data.error ?? 'Не удалось сменить пароль')
      }
    } finally {
      setBusy(false)
    }
  }

  return (
    <main className="page">
      <h1>Личный кабинет</h1>

      {verifiedParam === '1' && (
        <div className="banner banner-ok">Email подтверждён</div>
      )}
      {verifiedParam === '0' && (
        <div className="banner banner-bad">
          Ссылка подтверждения недействительна или устарела
        </div>
      )}

      <div className="profile-layout">
        <div className="card profile-settings">
          <h2>Профиль</h2>

          {/* Аватар: превью + загрузка/удаление. */}
          <div className="avatar-edit">
            <Avatar name={user.displayName} url={user.avatarUrl} size={72} />
            <div className="avatar-buttons">
              <input
                ref={fileRef}
                type="file"
                accept="image/*"
                hidden
                onChange={onAvatarPick}
              />
              <button
                type="button"
                className="secondary small"
                onClick={() => fileRef.current?.click()}
                disabled={busy}
              >
                {user.avatarUrl ? 'Сменить фото' : 'Загрузить фото'}
              </button>
              {user.avatarUrl && (
                <button
                  type="button"
                  className="btn-link"
                  onClick={onAvatarRemove}
                  disabled={busy}
                >
                  Удалить
                </button>
              )}
            </div>
          </div>

          <p className="muted">Телефон: {user.phone}</p>

      <form className="form" onSubmit={save}>
        <label>
          Фамилия и Имя
          <input value={name} onChange={(e) => setName(e.target.value)} required />
          {errors.name && <span className="field-error">{errors.name}</span>}
        </label>
        <label>
          Ник (необязательно)
          <input
            value={nickname}
            placeholder="как показывать в сетке"
            onChange={(e) => setNickname(e.target.value)}
          />
          {errors.nickname && <span className="field-error">{errors.nickname}</span>}
        </label>
        <label>
          Telegram (необязательно)
          <input
            value={telegram}
            placeholder="@username"
            onChange={(e) => setTelegram(e.target.value)}
          />
          {errors.telegram && <span className="field-error">{errors.telegram}</span>}
        </label>
        <label>
          Email
          <input
            type="email"
            inputMode="email"
            autoComplete="email"
            placeholder="you@example.com"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
          />
          {errors.email && <span className="field-error">{errors.email}</span>}
        </label>

        {/* Рейтинг RTTF (самозапись). На турнир с рейтингом выше 250 не пустят. */}
        <div className="rttf">
          <label className="checkbox">
            <input
              type="checkbox"
              checked={hasRating}
              onChange={(e) => setHasRating(e.target.checked)}
            />
            У меня есть рейтинг на rttf.ru
          </label>
          {hasRating && (
            <label>
              Рейтинг RTTF
              <input
                type="number"
                inputMode="numeric"
                min={0}
                placeholder="например, 180"
                value={rating}
                onChange={(e) => setRating(e.target.value)}
              />
              {errors.rttfRating && (
                <span className="field-error">{errors.rttfRating}</span>
              )}
              <span className="hint">
                На турнир пускают игроков с рейтингом до 250 включительно (или без
                рейтинга).
              </span>
            </label>
          )}
        </div>

        {/* Статус подтверждения показываем только если email реально задан. */}
        {user.email && (
          <div className="email-status">
            {user.emailVerified ? (
              <span className="ping-ok">✓ Email подтверждён</span>
            ) : (
              <>
                <span className="ping-bad">Email не подтверждён</span>
                <span className="hint">
                  Мы отправили ссылку на {user.email}. Откройте письмо и перейдите по
                  ссылке, чтобы подтвердить адрес.
                </span>
                <button
                  type="button"
                  className="secondary"
                  onClick={resend}
                  disabled={busy}
                >
                  Отправить письмо ещё раз
                </button>
              </>
            )}
          </div>
        )}

        {notice && <div className="form-note">{notice}</div>}
        {isDirty && (
          <button type="submit" disabled={busy}>
            {busy ? 'Сохраняю…' : 'Сохранить'}
          </button>
        )}
      </form>

          {/* Смена пароля — отдельная форма. */}
          <details className="password-block">
            <summary>Сменить пароль</summary>
            <form className="form" onSubmit={changePassword}>
              <label>
                Текущий пароль
                <input
                  type="password"
                  autoComplete="current-password"
                  value={curPass}
                  onChange={(e) => setCurPass(e.target.value)}
                  required
                />
              </label>
              <label>
                Новый пароль
                <input
                  type="password"
                  autoComplete="new-password"
                  placeholder="минимум 6 символов"
                  value={newPass}
                  onChange={(e) => setNewPass(e.target.value)}
                  required
                />
              </label>
              {pwErr && <div className="form-error">{pwErr}</div>}
              {pwMsg && <div className="form-note">{pwMsg}</div>}
              <button type="submit" disabled={busy}>
                Изменить пароль
              </button>
            </form>
          </details>

          <button type="button" className="secondary logout" onClick={onLogout}>
            Выйти
          </button>
        </div>

      {/* История выступлений по турнирам. */}
      <section className="card history">
        <h2>Мои турниры</h2>
        {history === null ? (
          <p className="muted">Загрузка…</p>
        ) : history.length === 0 ? (
          <p className="muted">Ты ещё не играл в турнирах.</p>
        ) : (
          <>
            <p className="muted">
              Всего игр: {totalGames} · побед: {totalWins}
            </p>
            <ul className="history-list">
              {history.map((t) => (
                <li key={t.number}>
                  <div className="h-head">
                    <Link to={`/tournaments/${t.id}/bracket`} className="h-title">
                      Турнир #{t.number}
                    </Link>
                    <span className="h-date">{formatDate(t.date)}</span>
                  </div>
                  <div className="h-body">
                    <span className="h-stage">{t.stage}</span>
                    <span className="h-score">
                      В{t.wins} · П{t.losses}
                    </span>
                  </div>
                </li>
              ))}
            </ul>
          </>
        )}
      </section>
      </div>
    </main>
  )
}
