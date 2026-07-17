import type { Bracket } from '../types'

// Запросы к сетке и отметке результата.

export async function getBracket(id: number): Promise<Bracket> {
  const res = await fetch(`/api/tournaments/${id}/bracket`, {
    credentials: 'include',
  })
  if (!res.ok) throw new Error('Не удалось загрузить сетку')
  return res.json()
}

// Отметить победителя матча. Доступно админу или участнику матча (проверка на бэке).
// walkover=true — техпобеда (соперник не явился), в статистику не идёт.
export async function markWinner(
  matchId: number,
  winnerId: number,
  walkover = false,
): Promise<{ ok: boolean; error?: string }> {
  const res = await fetch(`/api/matches/${matchId}/winner`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include',
    body: JSON.stringify({ winnerId, walkover }),
  })
  const data = await res.json().catch(() => ({}))
  return res.ok ? { ok: true } : { ok: false, error: data.error ?? 'Ошибка' }
}
