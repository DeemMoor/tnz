import { useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'

// ForgotPasswordPage — запрос ссылки для сброса пароля по телефону.
// Письмо уходит, только если у аккаунта есть подтверждённый email —
// но ответ всегда одинаковый, чтобы не палить, есть ли такой аккаунт.
export default function ForgotPasswordPage() {
  const [phone, setPhone] = useState('')
  const [sent, setSent] = useState(false)
  const [common, setCommon] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setCommon(null)
    setBusy(true)
    try {
      await fetch('/api/forgot-password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ phone }),
      })
      // Бэк всегда отвечает 200 — успех показываем независимо от содержимого ответа.
      setSent(true)
    } catch {
      setCommon('Сервер недоступен, попробуйте позже')
    } finally {
      setBusy(false)
    }
  }

  if (sent) {
    return (
      <main className="page">
        <h1>Забыли пароль?</h1>
        <p>
          Если на этот телефон зарегистрирован аккаунт с подтверждённым email — на почту пришла
          ссылка для сброса пароля (действует 1 час). Проверьте почту, в том числе папку «Спам».
        </p>
        <p className="muted">
          Email не подтверждён или не привязан? Обратитесь к администратору турнира.
        </p>
      </main>
    )
  }

  return (
    <main className="page">
      <h1>Забыли пароль?</h1>
      <p className="muted">Пришлём ссылку для сброса на email, привязанный к аккаунту.</p>
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
        {common && <div className="form-error">{common}</div>}
        <button type="submit" disabled={busy}>
          {busy ? 'Отправляю…' : 'Прислать ссылку'}
        </button>
      </form>
      <p className="muted">
        Вспомнили пароль? <Link to="/login">Войти</Link>
      </p>
    </main>
  )
}
