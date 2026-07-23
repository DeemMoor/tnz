import type { Roster } from '../types'

// Запросы к админским эндпоинтам чекина. Все — под ROLE_ADMIN на бэке.

export async function getRoster(id: number): Promise<Roster> {
  const res = await fetch(`/api/admin/tournaments/${id}/roster`, {
    credentials: 'include',
  })
  if (!res.ok) throw new Error('Не удалось загрузить ростер')
  return res.json()
}

export async function adminCheckIn(id: number, userId: number): Promise<Roster> {
  const res = await fetch(`/api/admin/tournaments/${id}/checkin/${userId}`, {
    method: 'POST',
    credentials: 'include',
  })
  if (!res.ok) throw new Error('Не удалось отметить игрока')
  return res.json()
}

export async function adminUncheckIn(id: number, userId: number): Promise<Roster> {
  const res = await fetch(`/api/admin/tournaments/${id}/checkin/${userId}`, {
    method: 'DELETE',
    credentials: 'include',
  })
  if (!res.ok) throw new Error('Не удалось снять отметку')
  return res.json()
}

export async function walkIn(
  id: number,
  phone: string,
  name: string,
): Promise<{ ok: boolean; roster?: Roster; error?: string }> {
  const res = await fetch(`/api/admin/tournaments/${id}/walk-in`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include',
    body: JSON.stringify({ phone, name }),
  })
  const data = await res.json().catch(() => ({}))
  return res.ok
    ? { ok: true, roster: data }
    : { ok: false, error: data.error ?? 'Не удалось добавить игрока' }
}

export async function closeCheckin(id: number): Promise<Roster> {
  const res = await fetch(`/api/admin/tournaments/${id}/close-checkin`, {
    method: 'POST',
    credentials: 'include',
  })
  if (!res.ok) throw new Error('Не удалось закрыть чекин')
  return res.json()
}

export async function drawTournament(
  id: number,
): Promise<{ ok: boolean; error?: string; tables?: { table1: number; table2: number } }> {
  const res = await fetch(`/api/admin/tournaments/${id}/draw`, {
    method: 'POST',
    credentials: 'include',
  })
  const data = await res.json().catch(() => ({}))
  return res.ok
    ? { ok: true, tables: data.tables }
    : { ok: false, error: data.error ?? 'Не удалось провести жеребьёвку' }
}
