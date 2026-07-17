import { useEffect } from 'react'
import { useLocation } from 'react-router-dom'

// Номер счётчика Яндекс.Метрики (см. index.html).
const COUNTER_ID = 110822273

declare global {
  interface Window {
    ym?: (id: number, action: string, url?: string) => void
  }
}

// MetrikaTracker — отправляет «просмотр страницы» в Метрику при клиентском
// переходе (SPA), иначе Метрика видит только первую загрузку.
export default function MetrikaTracker() {
  const location = useLocation()

  useEffect(() => {
    window.ym?.(COUNTER_ID, 'hit', window.location.href)
  }, [location])

  return null
}
