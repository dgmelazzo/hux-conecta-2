// src/components/shared/KpiCard.tsx
import { TrendingUp, TrendingDown, Minus, LucideIcon } from 'lucide-react'
import { cn } from '@/lib/utils'

interface KpiCardProps {
  label: string
  value: string | number
  icon: LucideIcon
  change?: number          // % vs período anterior — positivo sobe, negativo desce
  changeLabel?: string     // ex: "vs mês anterior"
  progress?: number        // 0–100, opcional
  className?: string
  loading?: boolean
}

export default function KpiCard({
  label, value, icon: Icon,
  change, changeLabel = 'vs mês anterior',
  progress, className, loading = false,
}: KpiCardProps) {

  if (loading) return <KpiCardSkeleton />

  const isPositive = change !== undefined && change > 0
  const isNegative = change !== undefined && change < 0
  const isNeutral  = change === undefined || change === 0

  return (
    <div className={cn(
      'bg-surface rounded-lg p-6 shadow-soft-sm',
      'transition-fast hover:shadow-soft-hover hover:-translate-y-px',
      className
    )}>
      {/* Header */}
      <div className="flex items-center justify-between mb-4">
        <span className="text-sm font-medium text-content-secondary">{label}</span>
        <div className="w-9 h-9 rounded-md bg-primary-light flex items-center justify-center shrink-0">
          <Icon className="w-5 h-5 text-primary" />
        </div>
      </div>

      {/* Valor */}
      <div className="tabular-nums text-3xl font-bold text-content mb-2 leading-none">
        {value}
      </div>

      {/* Variação */}
      {change !== undefined && (
        <div className={cn(
          'flex items-center gap-1 text-sm font-medium',
          isPositive && 'text-success',
          isNegative && 'text-danger',
          isNeutral  && 'text-content-muted',
        )}>
          {isPositive && <TrendingUp className="w-4 h-4" />}
          {isNegative && <TrendingDown className="w-4 h-4" />}
          {isNeutral  && <Minus className="w-4 h-4" />}
          <span>
            {isPositive ? '+' : ''}{change?.toFixed(1)}% {changeLabel}
          </span>
        </div>
      )}

      {/* Progress bar opcional */}
      {progress !== undefined && (
        <div className="mt-4">
          <div className="flex justify-between text-xs text-content-muted mb-1">
            <span>Meta</span>
            <span>{progress}%</span>
          </div>
          <div className="h-1.5 bg-surface-muted rounded-full overflow-hidden">
            <div
              className="h-full bg-primary rounded-full transition-all duration-slow"
              style={{ width: `${Math.min(progress, 100)}%` }}
            />
          </div>
        </div>
      )}
    </div>
  )
}

function KpiCardSkeleton() {
  return (
    <div className="bg-surface rounded-lg p-6 shadow-soft-sm animate-pulse">
      <div className="flex items-center justify-between mb-4">
        <div className="h-4 w-28 bg-surface-muted rounded" />
        <div className="w-9 h-9 bg-surface-muted rounded-md" />
      </div>
      <div className="h-9 w-32 bg-surface-muted rounded mb-2" />
      <div className="h-4 w-40 bg-surface-muted rounded" />
    </div>
  )
}
