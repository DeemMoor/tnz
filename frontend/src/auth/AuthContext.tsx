import {
  createContext,
  useContext,
  useEffect,
  useState,
  type ReactNode,
} from 'react'
import type { Me } from '../types'

// Что «раздаёт» контекст авторизации во всё приложение.
type AuthContextValue = {
  user: Me | null // текущий пользователь или null, если не залогинен
  loading: boolean // пока true — ещё выясняем, залогинен ли (первичная проверка)
  login: (phone: string, password: string) => Promise<void>
  logout: () => Promise<void>
  refresh: () => Promise<void> // перечитать /api/me (после смены профиля и т.п.)
}

// Ошибка входа/регистрации с полем-сообщением — чтобы формы могли её показать.
export class AuthError extends Error {}

const AuthContext = createContext<AuthContextValue | null>(null)

// AuthProvider — компонент-обёртка. Оборачиваем им приложение (в main.tsx),
// и тогда любой вложенный компонент через useAuth() получает user/login/logout.
export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<Me | null>(null)
  const [loading, setLoading] = useState(true)

  // Сходить на /api/me и обновить состояние пользователя.
  async function refresh() {
    try {
      const res = await fetch('/api/me', { credentials: 'include' })
      setUser(res.ok ? await res.json() : null)
    } catch {
      setUser(null)
    }
  }

  // При старте приложения один раз проверяем, есть ли живая сессия.
  useEffect(() => {
    refresh().finally(() => setLoading(false))
  }, [])

  async function login(phone: string, password: string) {
    const res = await fetch('/api/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ phone, password }),
    })
    if (!res.ok) {
      throw new AuthError('Неверный телефон или пароль')
    }
    setUser(await res.json())
  }

  async function logout() {
    await fetch('/api/logout', { method: 'POST', credentials: 'include' })
    setUser(null)
  }

  return (
    <AuthContext.Provider value={{ user, loading, login, logout, refresh }}>
      {children}
    </AuthContext.Provider>
  )
}

// useAuth — короткий доступ к контексту из любого компонента.
export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext)
  if (!ctx) {
    throw new Error('useAuth должен использоваться внутри <AuthProvider>')
  }
  return ctx
}
