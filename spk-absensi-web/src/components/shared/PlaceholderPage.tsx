import { cn } from '@/lib/utils'
import type { LucideIcon } from 'lucide-react'

interface PlaceholderPageProps {
  title: string
  description: string
  icon: LucideIcon
  accentColor?: string
}

export default function PlaceholderPage({
  title,
  description,
  icon: Icon,
  accentColor = 'bg-primary',
}: PlaceholderPageProps) {
  return (
    <div className="space-y-6">
      {/* Page header */}
      <div className="flex items-center gap-4">
        <div className={cn('w-10 h-10 rounded-xl flex items-center justify-center', accentColor)}>
          <Icon size={20} className="text-white" />
        </div>
        <div>
          <h1 className="text-xl font-bold text-foreground">{title}</h1>
          <p className="text-sm text-muted-foreground">{description}</p>
        </div>
      </div>

      {/* Placeholder content card */}
      <div className="bg-card border border-border rounded-2xl p-8 flex flex-col items-center justify-center gap-4 min-h-64 text-center">
        <div className={cn('w-16 h-16 rounded-2xl flex items-center justify-center opacity-20', accentColor)}>
          <Icon size={32} className="text-white" />
        </div>
        <div>
          <p className="font-semibold text-foreground">{title}</p>
          <p className="text-sm text-muted-foreground mt-1">
            Halaman ini sedang dalam pengembangan. <br />
            Fitur akan segera tersedia.
          </p>
        </div>
        <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-yellow-50 border border-yellow-200 text-yellow-700 text-xs font-medium">
          <span className="w-1.5 h-1.5 rounded-full bg-yellow-500 animate-pulse" />
          Segera Hadir
        </span>
      </div>
    </div>
  )
}
