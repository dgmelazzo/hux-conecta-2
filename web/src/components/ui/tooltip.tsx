import * as TooltipPrimitive from '@radix-ui/react-tooltip'
import { cn } from '@/lib/utils'

const TooltipProvider = TooltipPrimitive.Provider
const Tooltip = TooltipPrimitive.Root
const TooltipTrigger = TooltipPrimitive.Trigger

const TooltipContent = ({ className, ...props }: React.ComponentPropsWithoutRef<typeof TooltipPrimitive.Content>) => (
  <TooltipPrimitive.Content
    className={cn('z-50 rounded bg-gray-900 px-2 py-1 text-xs text-white', className)}
    {...props}
  />
)

export { Tooltip, TooltipTrigger, TooltipContent, TooltipProvider }
