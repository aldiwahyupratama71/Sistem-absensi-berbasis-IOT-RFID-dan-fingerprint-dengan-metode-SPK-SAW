import { cn } from '@/lib/utils'
import {
  BarChart3,
  Briefcase,
  CalendarCheck2,
  ChevronLeft,
  ClipboardList,
  FileText,
  LayoutDashboard,
  Monitor,
  Users,
} from 'lucide-react'
import { NavLink } from 'react-router-dom'

interface SidebarProps {
  collapsed: boolean
  onToggle: () => void
}

interface NavItem {
  label: string
  path: string
  icon: React.ComponentType<{ size?: number; className?: string }>
}

const navItems: NavItem[] = [
  { label: 'Dashboard', path: '/dashboard', icon: LayoutDashboard },
  { label: 'Data Karyawan', path: '/karyawan', icon: Users },
  { label: 'Perangkat IoT', path: '/perangkat', icon: Monitor },
  { label: 'Data Absensi', path: '/absensi', icon: CalendarCheck2 },
  { label: 'Kontrak & Periode', path: '/kontrak', icon: Briefcase },
  { label: 'Evaluasi SPK', path: '/evaluasi', icon: ClipboardList },
  { label: 'Laporan', path: '/laporan', icon: FileText },
]

export default function Sidebar({ collapsed, onToggle }: SidebarProps) {
  return (
    <aside
      className={cn(
        'relative flex flex-col h-full bg-sidebar transition-all duration-300 ease-in-out border-r border-sidebar-border',
        collapsed ? 'w-16' : 'w-64',
      )}
    >
      {/* Brand */}
      <div className="flex items-center h-16 px-4 border-b border-sidebar-border overflow-hidden">
        <div className="flex items-center gap-3 shrink-0">
          <div className="w-8 h-8 rounded-lg bg-primary flex items-center justify-center shrink-0">
            <BarChart3 size={16} className="text-white" />
          </div>
          {!collapsed && (
            <div className="animate-fade-in whitespace-nowrap">
              <p className="text-sidebar-foreground font-semibold text-sm leading-tight">SPK Absensi</p>
              <p className="text-sidebar-foreground/50 text-xs">Sistem Pendukung Keputusan</p>
            </div>
          )}
        </div>
      </div>

      {/* Nav */}
      <nav className="flex-1 p-3 space-y-1 overflow-y-auto overflow-x-hidden">
        {!collapsed && (
          <p className="text-sidebar-foreground/40 text-[10px] uppercase tracking-widest font-semibold px-3 mb-2 mt-1">
            Menu Utama
          </p>
        )}
        {navItems.map((item) => (
          <NavLink
            key={item.path}
            to={item.path}
            title={collapsed ? item.label : undefined}
            className={({ isActive }) =>
              cn('sidebar-nav-item', isActive && 'active', collapsed && 'justify-center px-2')
            }
          >
            <item.icon size={18} className="shrink-0" />
            {!collapsed && (
              <span className="animate-fade-in truncate">{item.label}</span>
            )}
          </NavLink>
        ))}
      </nav>

      {/* Collapse toggle */}
      <div className="p-3 border-t border-sidebar-border">
        <button
          onClick={onToggle}
          className={cn(
            'sidebar-nav-item w-full',
            collapsed ? 'justify-center px-2' : 'justify-between',
          )}
          title={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
        >
          {!collapsed && <span className="text-xs">Ciutkan Menu</span>}
          <ChevronLeft
            size={16}
            className={cn('shrink-0 transition-transform duration-300', collapsed && 'rotate-180')}
          />
        </button>
      </div>
    </aside>
  )
}
