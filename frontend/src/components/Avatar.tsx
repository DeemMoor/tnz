// Avatar — картинка игрока или цветной кружок с первой буквой имени (фолбэк).

// Стабильный цвет из строки (чтобы у игрока всегда был один и тот же).
function colorFor(name: string): string {
  let hash = 0
  for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash)
  const hue = Math.abs(hash) % 360
  return `hsl(${hue}, 45%, 45%)`
}

export default function Avatar({
  name,
  url,
  size = 40,
}: {
  name: string
  url?: string | null
  size?: number
}) {
  const letter = name.trim().charAt(0).toUpperCase() || '?'

  if (url) {
    return (
      <img
        className="avatar"
        src={url}
        alt={name}
        width={size}
        height={size}
        style={{ width: size, height: size }}
      />
    )
  }

  return (
    <span
      className="avatar avatar-letter"
      style={{
        width: size,
        height: size,
        background: colorFor(name),
        fontSize: size * 0.42,
      }}
    >
      {letter}
    </span>
  )
}
