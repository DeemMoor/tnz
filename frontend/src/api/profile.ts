import type { Me } from '../types'

// Загрузка аватарки (multipart). Возвращает обновлённого пользователя.
export async function uploadAvatar(
  file: File,
): Promise<{ ok: boolean; user?: Me; error?: string }> {
  const form = new FormData()
  form.append('avatar', file)
  const res = await fetch('/api/me/avatar', {
    method: 'POST',
    credentials: 'include',
    body: form,
  })
  const data = await res.json().catch(() => ({}))
  return res.ok ? { ok: true, user: data } : { ok: false, error: data.error ?? 'Ошибка загрузки' }
}

export async function deleteAvatar(): Promise<Me | null> {
  const res = await fetch('/api/me/avatar', {
    method: 'DELETE',
    credentials: 'include',
  })
  return res.ok ? res.json() : null
}
