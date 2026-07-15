import { Navigate } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'
import type { ReactNode } from 'react'

// ProtectedRoute — «вышибала» для приватных страниц.
// Пока идёт первичная проверка сессии — показываем заглушку.
// Не залогинен — перенаправляем на /login. Иначе показываем содержимое.
export default function ProtectedRoute({ children }: { children: ReactNode }) {
  const { user, loading } = useAuth()

  if (loading) {
    return <main className="page">Загрузка…</main>
  }
  if (!user) {
    return <Navigate to="/login" replace />
  }
  return <>{children}</>
}
