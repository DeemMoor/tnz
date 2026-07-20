import type { Bracket, Table1Loser } from '../types'

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

// Проигравшие на столе 1, доступные для подсадки в bye-слот стола 2 (только админ).
export async function getTable1Losers(tournamentId: number): Promise<Table1Loser[]> {
  const res = await fetch(`/api/admin/tournaments/${tournamentId}/table1-losers`, {
    credentials: 'include',
  })
  if (!res.ok) throw new Error('Не удалось загрузить список проигравших')
  const data = await res.json()
  return data.losers
}

// Подсадить проигравшего со стола 1 в пустой bye-слот стола 2 (только админ).
export async function fillBye(
  tournamentId: number,
  matchId: number,
  playerId: number,
): Promise<{ ok: boolean; error?: string }> {
  const res = await fetch(`/api/admin/tournaments/${tournamentId}/matches/${matchId}/fill-bye`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include',
    body: JSON.stringify({ playerId }),
  })
  const data = await res.json().catch(() => ({}))
  return res.ok ? { ok: true } : { ok: false, error: data.error ?? 'Ошибка' }
}

// Подсадить в bye-слот нового (или ещё не участвовавшего) игрока по телефону+имени.
export async function fillByeWalkIn(
  tournamentId: number,
  matchId: number,
  phone: string,
  name: string,
): Promise<{ ok: boolean; error?: string }> {
  const res = await fetch(`/api/admin/tournaments/${tournamentId}/matches/${matchId}/fill-bye-walkin`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include',
    body: JSON.stringify({ phone, name }),
  })
  const data = await res.json().catch(() => ({}))
  return res.ok ? { ok: true } : { ok: false, error: data.error ?? 'Ошибка' }
}
