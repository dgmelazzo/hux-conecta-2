import { create } from 'zustand'
import { persist } from 'zustand/middleware'

interface Tenant {
  id: number
  nome: string
  slug: string
  logo_url?: string
}

interface TenantState {
  tenant: Tenant | null
  setTenant: (tenant: Tenant) => void
}

export const useTenantStore = create<TenantState>()(
  persist(
    (set) => ({
      tenant: { id: 1, nome: 'ACIC-DF', slug: 'acicdf' },
      setTenant: (tenant) => set({ tenant }),
    }),
    { name: 'crm-tenant' }
  )
)
