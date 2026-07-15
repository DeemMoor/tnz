import { Link, NavLink, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'

// Header — общая шапка с навигацией. Рендерится на всех страницах,
// поэтому вернуться на главную/в разделы можно откуда угодно.
export default function Header() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()

  async function onLogout() {
    await logout()
    navigate('/')
  }

  return (
    <header className="app-header">
      <div className="app-header-inner">
        <Link to="/" className="brand">
          Теннис на Новой Земле
        </Link>
        <nav className="nav">
          <NavLink to="/tournaments">Турниры</NavLink>
          <NavLink to="/stats">Статистика</NavLink>
          {user ? (
            <>
              <NavLink to="/profile">Кабинет</NavLink>
              <button type="button" className="nav-logout" onClick={onLogout}>
                Выйти
              </button>
            </>
          ) : (
            <NavLink to="/login">Войти</NavLink>
          )}
        </nav>
      </div>
    </header>
  )
}
