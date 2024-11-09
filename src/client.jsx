
// src/api/client.js - API client setup
import axios from 'axios'
import toast from 'react-hot-toast'
import { useAuthStore } from '../store/auth'

const baseURL = import.meta.env.VITE_API_URL || 'http://localhost:3000'

export const client = axios.create({
  baseURL,
  headers: {
    'Content-Type': 'application/json',
  },
})

client.interceptors.request.use((config) => {
  const token = useAuthStore.getState().token
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

client.interceptors.response.use(
  (response) => response,
  (error) => {
    const message = error.response?.data?.error || 'An error occurred'
    toast.error(message)
    if (error.response?.status === 401) {
      useAuthStore.getState().logout()
    }
    return Promise.reject(error)
  }
)
