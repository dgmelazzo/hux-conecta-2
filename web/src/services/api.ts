// src/services/api.ts
import axios from 'axios'
import { useAuthStore } from '@/stores/authStore'
import { useTenantStore } from '@/stores/tenantStore'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? '/api',
  headers: { 'Content-Type': 'application/json' },
  timeout: 15_000,
})

// ── Request: injeta JWT + tenant slug ─────────────────────
api.interceptors.request.use((config) => {
  const token  = useAuthStore.getState().accessToken
  const tenant = useTenantStore.getState().tenant?.slug

  if (token)  config.headers.Authorization = `Bearer ${token}`
  if (tenant) config.headers['X-Tenant']   = tenant

  return config
})

// ── Response: renova token expirado (401) ─────────────────
let refreshing = false
let queue: Array<(token: string) => void> = []

api.interceptors.response.use(
  (res) => res,
  async (err) => {
    const original = err.config

    if (err.response?.status === 401 && !original._retry) {
      original._retry = true

      if (refreshing) {
        return new Promise((resolve) => {
          queue.push((token) => {
            original.headers.Authorization = `Bearer ${token}`
            resolve(api(original))
          })
        })
      }

      refreshing = true

      try {
        const refresh = useAuthStore.getState().refreshToken
        const { data } = await axios.post('/api/auth/refresh', { refresh_token: refresh })

        const newToken = data.access_token
        useAuthStore.getState().setTokens(newToken, data.refresh_token)

        queue.forEach((cb) => cb(newToken))
        queue = []

        original.headers.Authorization = `Bearer ${newToken}`
        return api(original)
      } catch {
        useAuthStore.getState().logout()
        window.location.href = '/login'
      } finally {
        refreshing = false
      }
    }

    return Promise.reject(err)
  }
)

export default api
