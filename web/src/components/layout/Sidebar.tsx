// src/components/layout/Sidebar.tsx
import { NavLink, useLocation } from 'react-router-dom'
import {
  LayoutDashboard, Users, CreditCard, Kanban,
  MessageSquare, Link2, Settings, Building2,
  ChevronLeft, ChevronRight
} from 'lucide-react'
import { cn } from '@/lib/utils'
import { useUIStore } from '@/stores/uiStore'
import { useTenantStore } from '@/stores/tenantStore'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'

const NAV_GROUPS = [
  {
    label: 'Principal',
    items: [
      { to: '/dashboard',     icon: LayoutDashboard, label: 'Dashboard' },
      { to: '/associados',    icon: Users,           label: 'Associados' },
      { to: '/financeiro',    icon: CreditCard,      label: 'Financeiro' },
      { to: '/pipeline',      icon: Kanban,          label: 'Pipeline' },
    ],
  },
  {
    label: 'Gestão',
    items: [
      { to: '/relacionamento', icon: MessageSquare,  label: 'Relacionamento' },
      { to: '/links',          icon: Link2,          label: 'Links Importantes' },
      { to: '/configuracoes',  icon: Settings,       label: 'Configurações' },
    ],
  },
  {
    label: 'Conta',
    items: [
      { to: '/minha-empresa', icon: Building2,       label: 'Minha Empresa' },
    ],
  },
]

export default function Sidebar() {
  const { sidebarCollapsed, toggleSidebar } = useUIStore()
  const { tenant } = useTenantStore()

  return (
    <aside
      className={cn(
        'fixed left-0 top-0 h-screen z-30 flex flex-col',
        'bg-surface shadow-soft-lg border-r border-surface-muted',
        'transition-all duration-slow ease-out',
        sidebarCollapsed ? 'w-sidebar-collapsed' : 'w-sidebar'
      )}
    >
      {/* Logo */}
      <div className="flex items-center h-header px-4 border-b border-surface-muted shrink-0">
        {tenant?.logo_url ? (
          <img
            src={tenant.logo_url}
            alt={tenant.nome}
            className={cn('object-contain transition-all duration-slow',
              sidebarCollapsed ? 'h-7 w-7' : 'h-8 max-w-[140px]'
            )}
          />
        ) : (
          <div className={cn(
            'font-bold text-primary transition-all duration-slow truncate',
            sidebarCollapsed ? 'text-sm' : 'text-base'
          )}>
            {sidebarCollapsed ? 'CR' : (tenant?.nome ?? 'Conecta CRM')}
          </div>
        )}
      </div>

      {/* Navegação */}
      <nav className="flex-1 overflow-y-auto overflow-x-hidden py-4 px-2 space-y-6">
        {NAV_GROUPS.map((group) => (
          <div key={group.label}>
            {!sidebarCollapsed && (
              <p className="px-3 mb-1 text-xs font-500 text-content-muted uppercase tracking-wider">
                {group.label}
              </p>
            )}
            <ul className="space-y-0.5">
              {group.items.map(({ to, icon: Icon, label }) => (
                <li key={to}>
                  <NavItem
                    to={to}
                    icon={Icon}
                    label={label}
                    collapsed={sidebarCollapsed}
                  />
                </li>
              ))}
            </ul>
          </div>
        ))}
      </nav>

      {/* Botão colapsar */}
      <div className="p-2 border-t border-surface-muted shrink-0">
        <button
          onClick={toggleSidebar}
          className={cn(
            'w-full flex items-center gap-3 px-3 py-2 rounded-md',
            'text-content-muted hover:bg-surface-subtle hover:text-content',
            'transition-fast cursor-pointer'
          )}
          aria-label={sidebarCollapsed ? 'Expandir menu' : 'Colapsar menu'}
        >
          {sidebarCollapsed
            ? <ChevronRight className="w-4 h-4 shrink-0" />
            : <><ChevronLeft className="w-4 h-4 shrink-0" />
               <span className="text-sm">Colapsar</span></>
          }
        </button>
      </div>
    </aside>
  )
}

// ── NavItem ────────────────────────────────────────────────

interface NavItemProps {
  to: string
  icon: React.ElementType
  label: string
  collapsed: boolean
}

function NavItem({ to, icon: Icon, label, collapsed }: NavItemProps) {
  const item = (
    <NavLink
      to={to}
      className={({ isActive }) =>
        cn(
          'flex items-center gap-3 px-3 py-2 rounded-md text-sm transition-fast cursor-pointer',
          isActive
            ? 'bg-primary-light text-primary font-semibold'
            : 'text-content-secondary hover:bg-surface-subtle hover:text-content'
        )
      }
    >
      <Icon className="w-[18px] h-[18px] shrink-0" />
      {!collapsed && <span className="truncate">{label}</span>}
    </NavLink>
  )

  if (!collapsed) return item

  return (
    <Tooltip>
      <TooltipTrigger asChild>{item}</TooltipTrigger>
      <TooltipContent side="right">{label}</TooltipContent>
    </Tooltip>
  )
}
