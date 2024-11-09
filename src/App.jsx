// src/App.jsx
import { useEffect } from 'react'
import { Routes, Route, Navigate, useNavigate, useLocation } from 'react-router-dom'
import PropTypes from 'prop-types'  // Add this import
import { useAuthStore } from './store/auth'
import Layout from './components/Layout'
import Login from './pages/Login'
import Register from './pages/Register'
import Campaigns from './pages/Campaigns'
import CampaignCreate from './pages/CampaignCreate'
import CampaignDetails from './pages/CampaignDetails'
import Dashboard from './pages/Dashboard'
import NotFound from './pages/NotFound'

// Protected Route Component
function PrivateRoute({ children, roles = [] }) {
  const navigate = useNavigate()
  const location = useLocation()
  const { token, user } = useAuthStore()

  useEffect(() => {
    if (!token) {
      navigate('/login', { state: { from: location }, replace: true })
      return
    }

    if (roles.length > 0 && !roles.includes(user?.role)) {
      navigate('/campaigns', { replace: true })
    }
  }, [token, user, roles, navigate, location])

  if (!token) return null
  if (roles.length > 0 && !roles.includes(user?.role)) return null

  return children
}

// Add PropTypes for PrivateRoute
PrivateRoute.propTypes = {
  children: PropTypes.node.isRequired,
  roles: PropTypes.arrayOf(PropTypes.string)
}

PrivateRoute.defaultProps = {
  roles: []
}

// Auth Guard Component
function AuthGuard({ children }) {
  const navigate = useNavigate()
  const location = useLocation()
  const { token } = useAuthStore()

  useEffect(() => {
    if (token) {
      navigate(location.state?.from || '/campaigns', { replace: true })
    }
  }, [token, navigate, location])

  if (token) return null

  return children
}

// Add PropTypes for AuthGuard
AuthGuard.propTypes = {
  children: PropTypes.node.isRequired
}

export default function App() {
  return (
    <Routes>
      <Route path="/" element={<Layout />}>
        <Route index element={<Navigate to="/campaigns" replace />} />

        {/* Public Routes */}
        <Route path="campaigns" element={<Campaigns />} />
        <Route path="campaigns/:id" element={<CampaignDetails />} />

        {/* Protected Routes */}
        <Route
          path="campaigns/create"
          element={
            <PrivateRoute roles={['campaigner', 'admin']}>
              <CampaignCreate />
            </PrivateRoute>
          }
        />
        <Route
          path="dashboard"
          element={
            <PrivateRoute>
              <Dashboard />
            </PrivateRoute>
          }
        />

        {/* Auth Routes */}
        <Route
          path="login"
          element={
            <AuthGuard>
              <Login />
            </AuthGuard>
          }
        />
        <Route
          path="register"
          element={
            <AuthGuard>
              <Register />
            </AuthGuard>
          }
        />

        {/* 404 Route */}
        <Route path="*" element={<NotFound />} />
      </Route>
    </Routes>
  )
}

