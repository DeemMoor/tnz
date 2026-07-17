import { Link, NavLink } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'
import Avatar from './Avatar'

// Header — общая шапка с навигацией. Рендерится на всех страницах,
// поэтому вернуться на главную/в разделы можно откуда угодно.
export default function Header() {
  const { user } = useAuth()

  return (
    <header className="app-header">
      <div className="app-header-inner">
        <Link to="/" className="brand">
          Теннис на Новой Земле
        </Link>
        <nav className="nav">
          <NavLink to="/tournaments">Турниры</NavLink>
          <NavLink to="/champions">Чемпионы</NavLink>
          <NavLink to="/stats">Статистика</NavLink>
          {user ? (
            <NavLink to="/profile" className="nav-profile">
              <Avatar name={user.displayName} url={user.avatarUrl} size={26} />
              <span>Кабинет</span>
            </NavLink>
          ) : (
            <NavLink to="/login">Войти</NavLink>
          )}
        </nav>
      </div>
    </header>
  )
}
