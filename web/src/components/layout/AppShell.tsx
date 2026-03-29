// src/components/layout/AppShell.tsx
import { useState } from 'react'
import { Outlet } from 'react-router-dom'
import Sidebar from './Sidebar'
import Header from './Header'
import { cn } from '@/lib/utils'
import { useUIStore } from '@/stores/uiStore'

export default function AppShell() {
  const { sidebarCollapsed } = useUIStore()

  return (
    <div className="flex h-screen overflow-hidden bg-surface-base">
      <Sidebar />

      <div
        className={cn(
          'flex flex-col flex-1 overflow-hidden transition-all duration-slow ease-out',
          sidebarCollapsed ? 'ml-sidebar-collapsed' : 'ml-sidebar'
        )}
      >
        <Header />
        <main className="flex-1 overflow-y-auto p-6">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
