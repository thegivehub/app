// src/setupTests.js
import '@testing-library/jest-dom'
import { afterAll, afterEach, beforeAll } from 'vitest'
import { setupServer } from 'msw/node'
import { http, HttpResponse } from 'msw'
import { cleanup } from '@testing-library/react'

// Clean up after each test
afterEach(() => {
  cleanup()
})

// Define handlers for your API endpoints
export const handlers = [
  http.post('http://localhost:3000/auth/login', async ({ request }) => {
    const { email, password } = await request.json()

    if (email === 'test@example.com' && password === 'password123') {
      return HttpResponse.json({
        data: {
          token: 'fake-token',
          user: {
            id: '1',
            email: 'test@example.com',
            fullName: 'Test User',
            role: 'donor',
            kyc_status: 'pending'
          }
        }
      })
    }

    return new HttpResponse(
      JSON.stringify({ error: 'Invalid credentials' }),
      { status: 401 }
    )
  })
]

// Setup MSW server
export const server = setupServer(...handlers)

// Start server before all tests
beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))

// Reset handlers after each test
afterEach(() => server.resetHandlers())

// Clean up after all tests are done
afterAll(() => server.close())
