import { Link } from 'react-router-dom'

// ContactsPage — «Контакты и о турнире». Даёт региональную привязку для Яндекса
// (город Ростов-на-Дону) и текстовый контент для SEO.
export default function ContactsPage() {
  return (
    <main className="page">
      <h1>Контакты и о турнире</h1>

      <section className="prose">
        <p>
          <strong>Теннис на Новой Земле</strong> — еженедельные любительские турниры
          по настольному теннису от <strong>Surf Coffee&nbsp;x&nbsp;Atlas</strong> и
          кластера <strong>«Новая Земля»</strong>.
        </p>

        <h2>Где проходят</h2>
        <p>
          <strong>Ростов-на-Дону</strong>, городское пространство кластера
          «Новая Земля», <strong>ул. Седова, 5</strong>.
        </p>

        <h2>Как это работает</h2>
        <ul>
          <li>Турниры проходят <strong>по воскресеньям</strong>.</li>
          <li>Регистрация открывается в четверг — записывайся онлайн на сайте.</li>
          <li>Первые 32 участника попадают в сетку, остальные — в очередь ожидания.</li>
          <li>
            В день турнира — чекин, жеребьёвка на два стола и{' '}
            <Link to="/tournaments">живая турнирная сетка</Link>.
          </li>
        </ul>

        <h2>Ссылки</h2>
        <ul>
          <li>
            <Link to="/tournaments">Ближайшие турниры и регистрация</Link>
          </li>
          <li>
            <Link to="/champions">Чемпионы</Link> ·{' '}
            <Link to="/stats">Статистика игроков</Link>
          </li>
          <li>
            Телеграм-сообщество:{' '}
            <a href="https://t.me/+pW47cgFhCFQ2YTMy" target="_blank" rel="noreferrer">
              Теннис на Новой Земле
            </a>
          </li>
        </ul>
      </section>
    </main>
  )
}
