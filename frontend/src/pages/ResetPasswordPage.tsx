import { useState, type FormEvent } from 'react'
import { Link, useSearchParams } from 'react-router-dom'

// ResetPasswordPage — переход по ссылке из письма (?token=...), ввод нового пароля.
export default function ResetPasswordPage() {
  const [searchParams] = useSearchParams()
  const token = searchParams.get('token') ?? ''

  const [password, setPassword] = useState('')
  const [confirm, setConfirm] = useState('')
  const [done, setDone] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  if (token === '') {
    return (
      <main className="page">
        <h1>Сброс пароля</h1>
        <div className="form-error">Ссылка неполная — в ней нет токена. Запросите сброс ещё раз.</div>
        <p className="muted">
          <Link to="/forgot-password">Запросить сброс пароля</Link>
        </p>
      </main>
    )
  }

  if (done) {
    return (
      <main className="page">
        <h1>Пароль изменён</h1>
        <p>Теперь можно войти с новым паролем.</p>
        <p className="muted">
          <Link to="/login">Войти</Link>
        </p>
      </main>
    )
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    if (password !== confirm) {
      setError('Пароли не совпадают')
      return
    }
    setBusy(true)
    try {
      const res = await fetch('/api/reset-password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ token, newPassword: password }),
      })
      if (res.ok) {
        setDone(true)
        return
      }
      const data = await res.json().catch(() => ({}))
      setError(data.error ?? 'Не удалось изменить пароль')
    } catch {
      setError('Сервер недоступен, попробуйте позже')
    } finally {
      setBusy(false)
    }
  }

  return (
    <main className="page">
      <h1>Новый пароль</h1>
      <form className="form" onSubmit={onSubmit}>
        <label>
          Новый пароль
          <input
            type="password"
            autoComplete="new-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
          />
        </label>
        <label>
          Повторите пароль
          <input
            type="password"
            autoComplete="new-password"
            value={confirm}
            onChange={(e) => setConfirm(e.target.value)}
            required
          />
        </label>
        {error && <div className="form-error">{error}</div>}
        <button type="submit" disabled={busy}>
          {busy ? 'Сохраняю…' : 'Сохранить пароль'}
        </button>
      </form>
    </main>
  )
}
