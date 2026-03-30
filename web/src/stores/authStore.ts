import { create } from 'zustand'
import { persist } from 'zustand/middleware'

interface User {
  id: number
  nome: string
  email: string
  role: string
  tenant_id: number | null
}

interface AuthState {
  token: string | null
  accessToken: string | null
  refreshToken: string | null
  user: User | null
  setAuth: (token: string, user: User) => void
  setTokens: (accessToken: string, refreshToken: string | null) => void
  logout: () => void
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      token: null,
      accessToken: null,
      refreshToken: null,
      user: null,
      setAuth: (token, user) => set({ token, accessToken: token, user }),
      setTokens: (accessToken, refreshToken) => set({ token: accessToken, accessToken, refreshToken }),
      logout: () => set({ token: null, accessToken: null, refreshToken: null, user: null }),
    }),
    { name: 'crm-auth' }
  )
)
