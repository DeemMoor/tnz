import { useState, type FormEvent } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'

// LoginPage — форма входа по телефону + паролю.
export default function LoginPage() {
  const { login, user } = useAuth()
  const navigate = useNavigate()

  // state — «память» компонента: значения полей и текст ошибки.
  const [phone, setPhone] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  // Уже вошёл — форма входа не нужна, отправляем на главную.
  // (после хуков — чтобы не нарушать правила хуков React)
  if (user) return <Navigate to="/" replace />

  async function onSubmit(e: FormEvent) {
    e.preventDefault() // не перезагружать страницу при отправке формы
    setError(null)
    setBusy(true)
    try {
      await login(phone, password)
      navigate('/') // успех — на главную
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Ошибка входа')
    } finally {
      setBusy(false)
    }
  }

  return (
    <main className="page">
      <h1>Вход</h1>
      <form className="form" onSubmit={onSubmit}>
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
          Пароль
          <input
            type="password"
            autoComplete="current-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
          />
        </label>
        {error && <div className="form-error">{error}</div>}
        <button type="submit" disabled={busy}>
          {busy ? 'Вхожу…' : 'Войти'}
        </button>
      </form>
      <p className="muted">
        Нет аккаунта? <Link to="/register">Зарегистрироваться</Link>
      </p>
    </main>
  )
}
