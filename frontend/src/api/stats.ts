import type { PlayerStat } from '../types'

export async function getStats(): Promise<PlayerStat[]> {
  const res = await fetch('/api/stats')
  if (!res.ok) throw new Error('Не удалось загрузить статистику')
  const data = await res.json()
  return data.players
}
