import { useEffect, useState } from 'react'
import { getStack, type StackGroup, type StackItem } from '../api/stack'

// Логотип технологии: картинка из /img/stack, при ошибке — буква-бейдж.
function TechLogo({ tech }: { tech: StackItem }) {
  const [failed, setFailed] = useState(false)
  if (failed) {
    return (
      <span className="stack-logo-letter" aria-hidden="true">
        {tech.name.charAt(0)}
      </span>
    )
  }
  return (
    <img
      className="stack-logo"
      src={`/img/stack/${tech.logo}.svg`}
      alt=""
      width={30}
      height={30}
      onError={() => setFailed(true)}
    />
  )
}

// StackPage — «секретная» страница со стеком проекта. Не в меню, только по /stack.
// Версии подтягиваются с бэка (реальные, не захардкожены).
export default function StackPage() {
  const [groups, setGroups] = useState<StackGroup[] | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    getStack()
      .then(setGroups)
      .catch(() => setError('Не удалось загрузить стек'))
  }, [])

  return (
    <main className="page">
      <h1>Tech Stack</h1>
      <p className="muted">Реальные версии — подтягиваются с сервера.</p>

      {error && <div className="form-error">{error}</div>}
      {!groups && !error && <p>Загрузка…</p>}

      {groups?.map((group) => (
        <section key={group.title} className="stack-group">
          <h2>{group.title}</h2>
          <div className="stack-grid">
            {group.items.map((tech) => (
              <a
                key={tech.name}
                className="stack-card"
                href={tech.link}
                target="_blank"
                rel="noreferrer"
              >
                <TechLogo tech={tech} />
                <span className="stack-meta">
                  <span className="stack-name">{tech.name}</span>
                  <span className="stack-version">{tech.version}</span>
                </span>
              </a>
            ))}
          </div>
        </section>
      ))}

      <p className="stack-credit">Developed by Deem Moor</p>
    </main>
  )
}
