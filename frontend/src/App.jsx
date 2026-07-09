import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { AuthProvider, useAuth, useCan } from './features/auth/AuthContext'
import AppLayout from './components/AppLayout'
import LoginPage from './pages/LoginPage'
import RegisterPage from './pages/RegisterPage'
import DashboardPage from './pages/DashboardPage'
import TeamsPage from './pages/TeamsPage'
import TacticsPage from './pages/TacticsPage'
import SearchPage from './pages/SearchPage'
import AwardsPage from './pages/AwardsPage'
import AdminPage from './pages/AdminPage'
import ProfilePage from './pages/ProfilePage'
import './App.css'

function Protected({ children }) {
  const { user, loading } = useAuth()
  if (loading) return <p className="muted center">Loading…</p>
  if (!user) return <Navigate to="/login" replace />
  return children
}

// Admin-only route guard — non-admins are bounced home (the API enforces it regardless).
function AdminOnly({ children }) {
  const { user } = useAuth()
  return user?.is_admin ? children : <Navigate to="/" replace />
}

// App-ability route guard — a user without the ability is bounced home even if they type the
// URL. The API enforces it regardless (403); this just avoids a broken page.
function RequireCan({ ability, children }) {
  return useCan(ability) ? children : <Navigate to="/" replace />
}

export default function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          <Route path="/register" element={<RegisterPage />} />
          <Route
            element={
              <Protected>
                <AppLayout />
              </Protected>
            }
          >
            <Route path="/" element={<ProfilePage />} />
            <Route path="/matches" element={<DashboardPage />} />
            <Route path="/teams" element={<TeamsPage />} />
            <Route path="/tactics" element={<RequireCan ability="tactics.view"><TacticsPage /></RequireCan>} />
            <Route path="/search" element={<RequireCan ability="search.use"><SearchPage /></RequireCan>} />
            <Route path="/awards" element={<RequireCan ability="awards.view"><AwardsPage /></RequireCan>} />
            <Route path="/admin" element={<AdminOnly><AdminPage /></AdminOnly>} />
          </Route>
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  )
}
