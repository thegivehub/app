// src/__tests__/pages/Dashboard.test.jsx
import { render, screen } from '@testing-library/react'
import { describe, it, expect, beforeEach } from 'vitest'
import { BrowserRouter } from 'react-router-dom'
import Dashboard from '../../pages/Dashboard'
import { useAuthStore } from '../../store/auth'

const renderDashboard = () => {
  return render(
    <BrowserRouter>
      <Dashboard />
    </BrowserRouter>
  )
}

describe('Dashboard Component', () => {
  beforeEach(() => {
    useAuthStore.setState({
      token: null,
      user: null,
      loading: false,
      error: null
    })
  })

  it('shows login message when user is not authenticated', () => {
    renderDashboard()
    expect(screen.getByText(/please log in/i)).toBeInTheDocument()
  })

  it('displays user information when authenticated', () => {
    useAuthStore.setState({
      token: 'fake-token',
      user: {
        fullName: 'Test User',
        email: 'test@example.com',
        role: 'donor',
        kyc_status: 'pending'
      }
    })

    renderDashboard()
    expect(screen.getByText('Test User')).toBeInTheDocument()
  })
})
