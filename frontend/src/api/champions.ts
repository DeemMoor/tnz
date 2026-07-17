export type ChampionEntry = {
  tableNumber: number
  name: string
  avatarUrl: string | null
}

export type ChampionTournament = {
  number: number
  date: string
  champions: ChampionEntry[]
}

export async function getChampions(): Promise<ChampionTournament[]> {
  const res = await fetch('/api/champions')
  if (!res.ok) throw new Error('Не удалось загрузить чемпионов')
  const data = await res.json()
  return data.tournaments
}
