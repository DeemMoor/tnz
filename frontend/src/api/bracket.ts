import type { AvailablePlayer, Bracket } from '../types'

// Запросы к сетке и отметке результата.

type Result = { ok: boolean; error?: string }

// Общая обёртка: шлём JSON, разбираем ответ в { ok } или { ok:false, error }.
async function post(url: string, body?: unknown): Promise<Result> {
  const res = await fetch(url, {
    method: 'POST',
    headers: body !== undefined ? { 'Content-Type': 'application/json' } : undefined,
    credentials: 'include',
    body: body !== undefined ? JSON.stringify(body) : undefined,
  })
  const data = await res.json().catch(() => ({}))
  return res.ok ? { ok: true } : { ok: false, error: data.error ?? 'Ошибка' }
}

export async function getBracket(id: number): Promise<Bracket> {
  const res = await fetch(`/api/tournaments/${id}/bracket`, {
    credentials: 'include',
  })
  if (!res.ok) throw new Error('Не удалось загрузить сетку')
  return res.json()
}

// Отметить победителя матча. Доступно админу или участнику матча (проверка на бэке).
// walkover=true — техпобеда (соперник не явился), в статистику не идёт.
export function markWinner(matchId: number, winnerId: number, walkover = false): Promise<Result> {
  return post(`/api/matches/${matchId}/winner`, { winnerId, walkover })
}

// Отменить результат матча — вернуть в «не сыгран» (только админ).
export function clearMatch(matchId: number): Promise<Result> {
  return post(`/api/matches/${matchId}/clear`)
}

// Выбывшие игроки турнира — кандидаты на подсадку в свободный слот или замену.
export async function getAvailablePlayers(tournamentId: number): Promise<AvailablePlayer[]> {
  const res = await fetch(`/api/admin/tournaments/${tournamentId}/available-players`, {
    credentials: 'include',
  })
  if (!res.ok) throw new Error('Не удалось загрузить список игроков')
  const data = await res.json()
  return data.players
}

// Посадить выбывшего игрока в свободный слот матча 1-го тура (только админ).
export function fillBye(tournamentId: number, matchId: number, playerId: number): Promise<Result> {
  return post(`/api/admin/tournaments/${tournamentId}/matches/${matchId}/fill-bye`, { playerId })
}

// Посадить в свободный слот нового (или ещё не участвовавшего) игрока по телефону+имени.
export function fillByeWalkIn(
  tournamentId: number,
  matchId: number,
  phone: string,
  name: string,
): Promise<Result> {
  return post(`/api/admin/tournaments/${tournamentId}/matches/${matchId}/fill-bye-walkin`, {
    phone,
    name,
  })
}

// Заменить игрока в матче 1-го тура выбывшим игроком (только админ).
// Если матч был сыгран — победа уходит в статистику победителю, матч начинается заново.
export function replacePlayer(
  tournamentId: number,
  matchId: number,
  outgoingId: number,
  playerId: number,
): Promise<Result> {
  return post(`/api/admin/tournaments/${tournamentId}/matches/${matchId}/replace-player`, {
    outgoingId,
    playerId,
  })
}

// То же, но новым игроком по телефону+имени (пришёл только что).
export function replacePlayerWalkIn(
  tournamentId: number,
  matchId: number,
  outgoingId: number,
  phone: string,
  name: string,
): Promise<Result> {
  return post(`/api/admin/tournaments/${tournamentId}/matches/${matchId}/replace-player`, {
    outgoingId,
    phone,
    name,
  })
}

// Убрать игрока из сетки: слот пустеет, результат матча отменяется (только админ).
export function removePlayer(
  tournamentId: number,
  matchId: number,
  playerId: number,
): Promise<Result> {
  return post(`/api/admin/tournaments/${tournamentId}/matches/${matchId}/remove-player`, {
    playerId,
  })
}
