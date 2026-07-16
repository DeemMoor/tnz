import { useState, type FormEvent } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'

// Ответ бэка при ошибках валидации: { errors: { phone?: string, ... } }.
type RegisterErrors = Partial<Record<'phone' | 'password' | 'name', string>>

// RegisterPage — регистрация игрока: телефон, имя, пароль.
export default function RegisterPage() {
  const { login, user } = useAuth()
  const navigate = useNavigate()

  // Уже вошёл — регистрация не нужна.
  if (user) return <Navigate to="/" replace />

  const [phone, setPhone] = useState('')
  const [name, setName] = useState('')
  const [password, setPassword] = useState('')
  const [errors, setErrors] = useState<RegisterErrors>({})
  const [common, setCommon] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setErrors({})
    setCommon(null)
    setBusy(true)
    try {
      const res = await fetch('/api/register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ phone, name, password }),
      })
      if (res.status === 201) {
        // Успех — пробуем сразу войти. Если по какой-то причине не вышло,
        // аккаунт уже создан — ведём на страницу входа, а не пугаем ошибкой.
        try {
          await login(phone, password)
          navigate('/')
        } catch {
          navigate('/login')
        }
        return
      }
      const data = await res.json().catch(() => ({}))
      if (data.errors) {
        setErrors(data.errors as RegisterErrors)
      } else {
        setCommon('Не удалось зарегистрироваться, попробуйте ещё раз')
      }
    } catch {
      setCommon('Сервер недоступен')
    } finally {
      setBusy(false)
    }
  }

  return (
    <main className="page">
      <h1>Регистрация</h1>
      <form className="form" onSubmit={onSubmit}>
        <label>
          Фамилия и Имя
          <input
            type="text"
            autoComplete="name"
            placeholder="Иванов Иван"
            value={name}
            onChange={(e) => setName(e.target.value)}
            required
          />
          {errors.name && <span className="field-error">{errors.name}</span>}
        </label>
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
          {errors.phone && <span className="field-error">{errors.phone}</span>}
        </label>
        <label>
          Пароль
          <input
            type="password"
            autoComplete="new-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
          />
          {errors.password && (
            <span className="field-error">{errors.password}</span>
          )}
        </label>
        {common && <div className="form-error">{common}</div>}
        <button type="submit" disabled={busy}>
          {busy ? 'Регистрирую…' : 'Зарегистрироваться'}
        </button>
      </form>
      <p className="muted">
        Уже есть аккаунт? <Link to="/login">Войти</Link>
      </p>
    </main>
  )
}
