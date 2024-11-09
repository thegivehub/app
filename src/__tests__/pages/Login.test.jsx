// src/__tests__/pages/Login.test.jsx
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { BrowserRouter } from 'react-router-dom'
import { useAuthStore } from '../../store/auth'
import Login from '../../pages/Login'

// Mock navigation
const mockNavigate = vi.fn()
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom')
  return {
    ...actual,
    useNavigate: () => mockNavigate
  }
})

describe('Login Component', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    useAuthStore.setState({
      token: null,
      user: null,
      loading: false,
      error: null
    })
  })

  it('handles login failure', async () => {
    const { container } = render(
      <BrowserRouter>
        <Login />
      </BrowserRouter>
    )

    // Fill in the form with wrong credentials
    fireEvent.change(screen.getByLabelText(/email/i), {
      target: { value: 'wrong@example.com' }
    })
    fireEvent.change(screen.getByLabelText(/password/i), {
      target: { value: 'wrongpassword' }
    })

    // Submit the form
    fireEvent.click(screen.getByRole('button', { name: /sign in/i }))

    // Wait for error state
    await waitFor(() => {
      expect(screen.getByText(/invalid credentials/i)).toBeInTheDocument()
    })

    // Verify navigation wasn't called
    expect(mockNavigate).not.toHaveBeenCalled()
  })

  it('navigates on successful login', async () => {
    render(
      <BrowserRouter>
        <Login />
      </BrowserRouter>
    )

    // Fill in the form with correct credentials
    fireEvent.change(screen.getByLabelText(/email/i), {
      target: { value: 'test@example.com' }
    })
    fireEvent.change(screen.getByLabelText(/password/i), {
      target: { value: 'password123' }
    })

    // Submit the form
    fireEvent.click(screen.getByRole('button', { name: /sign in/i }))

    // Wait for navigation
    await waitFor(() => {
      expect(mockNavigate).toHaveBeenCalledWith('/campaigns')
    })
  })
})
