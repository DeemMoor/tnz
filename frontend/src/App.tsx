import { Routes, Route } from 'react-router-dom'
import HomePage from './pages/HomePage'
import LoginPage from './pages/LoginPage'
import RegisterPage from './pages/RegisterPage'
import ProfilePage from './pages/ProfilePage'
import TournamentsPage from './pages/TournamentsPage'
import CheckinPage from './pages/CheckinPage'
import BracketPage from './pages/BracketPage'
import ParticipantsPage from './pages/ParticipantsPage'
import ChampionsPage from './pages/ChampionsPage'
import ContactsPage from './pages/ContactsPage'
import StackPage from './pages/StackPage'
import StatsPage from './pages/StatsPage'
import ProtectedRoute from './components/ProtectedRoute'
import Header from './components/Header'
import Footer from './components/Footer'
import MetrikaTracker from './components/MetrikaTracker'
import './App.css'

// App — «корневой» компонент. Шапка сверху + роутинг + подвал.
// Обёртка .app-shell прижимает футер к низу на коротких страницах.
function App() {
  return (
    <div className="app-shell">
      <MetrikaTracker />
      <Header />
      <div className="app-main">
      <Routes>
      <Route path="/" element={<HomePage />} />
      <Route path="/tournaments" element={<TournamentsPage />} />
      <Route path="/checkin/:id" element={<CheckinPage />} />
      <Route path="/tournaments/:id/bracket" element={<BracketPage />} />
      <Route path="/tournaments/:id/participants" element={<ParticipantsPage />} />
      <Route path="/stats" element={<StatsPage />} />
      <Route path="/champions" element={<ChampionsPage />} />
      <Route path="/contacts" element={<ContactsPage />} />
      <Route path="/stack" element={<StackPage />} />
      <Route path="/login" element={<LoginPage />} />
      <Route path="/register" element={<RegisterPage />} />
      <Route
        path="/profile"
        element={
          <ProtectedRoute>
            <ProfilePage />
          </ProtectedRoute>
        }
      />
      </Routes>
      </div>
      <Footer />
    </div>
  )
}

export default App
