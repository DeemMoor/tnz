import type { Tournament, MyTournamentStat, Participants } from '../types'

// Слой запросов к API турниров — чтобы компоненты не дёргали fetch напрямую.

export async function listTournaments(): Promise<Tournament[]> {
  const res = await fetch('/api/tournaments', { credentials: 'include' })
  if (!res.ok) throw new Error('Не удалось загрузить турниры')
  return res.json()
}

// Ближайший незавершённый турнир (для главной). null — если такого нет.
export async function getNearestTournament(): Promise<Tournament | null> {
  const res = await fetch('/api/tournaments/nearest', { credentials: 'include' })
  if (!res.ok) throw new Error('Не удалось загрузить ближайший турнир')
  return res.json()
}

// Публичный список участников турнира.
export async function getParticipants(id: number): Promise<Participants> {
  const res = await fetch(`/api/tournaments/${id}/participants`)
  if (!res.ok) throw new Error('Не удалось загрузить участников')
  return res.json()
}

// История выступлений текущего игрока по турнирам.
export async function getMyTournaments(): Promise<MyTournamentStat[]> {
  const res = await fetch('/api/me/tournaments', { credentials: 'include' })
  if (!res.ok) throw new Error('Не удалось загрузить историю')
  const data = await res.json()
  return data.tournaments
}

// Возвращает { ok, tournament?, error? }: при отказе бэк присылает причину.
export async function registerForTournament(
  id: number,
): Promise<{ ok: boolean; tournament?: Tournament; error?: string }> {
  const res = await fetch(`/api/tournaments/${id}/register`, {
    method: 'POST',
    credentials: 'include',
  })
  const data = await res.json().catch(() => ({}))
  return res.ok
    ? { ok: true, tournament: data }
    : { ok: false, error: data.error ?? 'Не удалось записаться' }
}

export async function checkInSelf(
  id: number,
): Promise<{ ok: boolean; tournament?: Tournament; error?: string }> {
  const res = await fetch(`/api/tournaments/${id}/checkin`, {
    method: 'POST',
    credentials: 'include',
  })
  const data = await res.json().catch(() => ({}))
  return res.ok
    ? { ok: true, tournament: data }
    : { ok: false, error: data.error ?? 'Не удалось отметиться' }
}

export async function unregisterFromTournament(
  id: number,
): Promise<{ ok: boolean; tournament?: Tournament; error?: string }> {
  const res = await fetch(`/api/tournaments/${id}/registration`, {
    method: 'DELETE',
    credentials: 'include',
  })
  const data = await res.json().catch(() => ({}))
  return res.ok
    ? { ok: true, tournament: data }
    : { ok: false, error: data.error ?? 'Не удалось снять регистрацию' }
}
