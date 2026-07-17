export type StackItem = { name: string; logo: string; link: string; version: string }
export type StackGroup = { title: string; items: StackItem[] }

export async function getStack(): Promise<StackGroup[]> {
  const res = await fetch('/api/stack')
  if (!res.ok) throw new Error('Не удалось загрузить стек')
  const data = await res.json()
  return data.groups
}
