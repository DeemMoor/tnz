import type { PlayerCard } from '../types'

// Публичная карточка игрока: аватар, имя, рейтинг по внутренним турнирам.
export async function getPlayer(id: number): Promise<PlayerCard> {
  const res = await fetch(`/api/players/${id}`, { credentials: 'include' })
  if (!res.ok) throw new Error('Игрок не найден')
  return res.json()
}
