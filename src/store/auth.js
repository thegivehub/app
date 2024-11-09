// src/store/auth.js
import { create } from 'zustand'
import { persist, createJSONStorage } from 'zustand/middleware'
import axios from 'axios'
import { toast } from 'react-hot-toast'

// Initialize axios instance
const client = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:3000',
  headers: {
    'Content-Type': 'application/json'
  }
})

// Define initial state
const initialState = {
  token: null,
  user: null,
  loading: false,
  error: null,
}

export const useAuthStore = create(
  persist(
    (set, get) => ({
      // Initial state
      ...initialState,

      // Getters
      isAuthenticated: () => !!get().token,
      
      // Setter for auth data
      setAuth: (token, user) => {
        set({ token, user, error: null })
        // Update axios default header
        client.defaults.headers.common['Authorization'] = `Bearer ${token}`
      },

      // Clear auth data
      clearAuth: () => {
        set(initialState)
        delete client.defaults.headers.common['Authorization']
      },

      // Login action
      login: async (email, password) => {
        set({ loading: true, error: null })
        
        try {
          const response = await client.post('/auth/login', { email, password })
          const { token, user } = response.data.data
          
          // Set auth data
          set({ 
            token, 
            user, 
            loading: false, 
            error: null 
          })
          
          // Set axios auth header
          client.defaults.headers.common['Authorization'] = `Bearer ${token}`
          
          toast.success('Welcome back!')
          return { success: true }
        } catch (error) {
          const message = error.response?.data?.error || 'Login failed'
          set({ 
            token: null, 
            user: null, 
            loading: false, 
            error: message 
          })
          toast.error(message)
          return { success: false, error: message }
        }
      },

      // Register action
      register: async (userData) => {
        set({ loading: true, error: null })
        
        try {
          const response = await client.post('/auth/register', userData)
          const { token, user } = response.data.data
          
          set({ 
            token, 
            user, 
            loading: false, 
            error: null 
          })
          
          client.defaults.headers.common['Authorization'] = `Bearer ${token}`
          
          toast.success('Registration successful!')
          return { success: true }
        } catch (error) {
          const message = error.response?.data?.error || 'Registration failed'
          set({ 
            loading: false, 
            error: message 
          })
          toast.error(message)
          return { success: false, error: message }
        }
      },

      // Logout action
      logout: () => {
        set(initialState)
        delete client.defaults.headers.common['Authorization']
        localStorage.removeItem('auth-storage')
        toast.success('Logged out successfully')
      },

      // Update profile
      updateProfile: async (profileData) => {
        set({ loading: true, error: null })
        
        try {
          const response = await client.patch('/auth/profile', profileData)
          const { user } = response.data.data
          
          set(state => ({
            loading: false,
            user: { ...state.user, ...user },
            error: null
          }))
          
          toast.success('Profile updated successfully')
          return { success: true }
        } catch (error) {
          const message = error.response?.data?.error || 'Profile update failed'
          set({ loading: false, error: message })
          toast.error(message)
          return { success: false, error: message }
        }
      },

      // Update KYC status
      updateKYCStatus: async (kycData) => {
        set({ loading: true, error: null })
        
        try {
          const response = await client.post('/auth/kyc', kycData)
          const { user } = response.data.data
          
          set(state => ({
            loading: false,
            user: { ...state.user, ...user },
            error: null
          }))
          
          toast.success('KYC status updated')
          return { success: true }
        } catch (error) {
          const message = error.response?.data?.error || 'KYC update failed'
          set({ loading: false, error: message })
          toast.error(message)
          return { success: false, error: message }
        }
      },

      // Update Stellar wallet
      updateStellarWallet: async (walletAddress) => {
        set({ loading: true, error: null })
        
        try {
          const response = await client.post('/auth/wallet', {
            stellar_public_key: walletAddress
          })
          const { user } = response.data.data
          
          set(state => ({
            loading: false,
            user: { ...state.user, ...user },
            error: null
          }))
          
          toast.success('Wallet updated successfully')
          return { success: true }
        } catch (error) {
          const message = error.response?.data?.error || 'Wallet update failed'
          set({ loading: false, error: message })
          toast.error(message)
          return { success: false, error: message }
        }
      },

      // Request password reset
      requestPasswordReset: async (email) => {
        set({ loading: true, error: null })
        
        try {
          await client.post('/auth/reset-password-request', { email })
          set({ loading: false })
          toast.success('Password reset instructions sent to your email')
          return { success: true }
        } catch (error) {
          const message = error.response?.data?.error || 'Password reset request failed'
          set({ loading: false, error: message })
          toast.error(message)
          return { success: false, error: message }
        }
      },

      // Reset password
      resetPassword: async (token, newPassword) => {
        set({ loading: true, error: null })
        
        try {
          await client.post('/auth/reset-password', { token, newPassword })
          set({ loading: false })
          toast.success('Password reset successful')
          return { success: true }
        } catch (error) {
          const message = error.response?.data?.error || 'Password reset failed'
          set({ loading: false, error: message })
          toast.error(message)
          return { success: false, error: message }
        }
      },

      // Verify auth token
      verifyToken: async () => {
        const token = get().token
        if (!token) return false

        try {
          await client.get('/auth/verify')
          return true
        } catch (error) {
          get().logout()
          return false
        }
      },

      // Clear error
      clearError: () => set({ error: null }),

      // Check role
      hasRole: (requiredRole) => {
        const user = get().user
        return user?.role === requiredRole
      }
    }),
    {
      name: 'auth-storage', // unique name for localStorage
      storage: createJSONStorage(() => localStorage),
      // Only persist token and user
      partialize: (state) => ({
        token: state.token,
        user: state.user
      })
    }
  )
)

// Helper hooks
export const useAuth = () => useAuthStore()
export const useUser = () => useAuthStore(state => state.user)
export const useIsAuthenticated = () => useAuthStore(state => !!state.token)
export const useAuthLoading = () => useAuthStore(state => state.loading)
export const useAuthError = () => useAuthStore(state => state.error)

// Axios interceptor for automatic token handling
client.interceptors.response.use(
  response => response,
  error => {
    if (error.response?.status === 401) {
      useAuthStore.getState().logout()
    }
    return Promise.reject(error)
  }
)

// Export the API client for use in other parts of the app
export const api = client
