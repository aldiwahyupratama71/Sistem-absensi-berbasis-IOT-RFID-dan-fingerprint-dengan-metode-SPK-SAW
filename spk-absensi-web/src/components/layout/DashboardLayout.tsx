import Header from '@/components/layout/Header'
import Sidebar from '@/components/layout/Sidebar'
import { cn } from '@/lib/utils'
import { useState } from 'react'
import { Outlet } from 'react-router-dom'

export default function DashboardLayout() {
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false)
  const [mobileSidebarOpen, setMobileSidebarOpen] = useState(false)

  return (
    <div className="flex h-screen overflow-hidden bg-background">
      {/* ─── Desktop sidebar ─────────────────────────────────── */}
      <div className="hidden lg:flex">
        <Sidebar
          collapsed={sidebarCollapsed}
          onToggle={() => setSidebarCollapsed((v) => !v)}
        />
      </div>

      {/* ─── Mobile sidebar overlay ───────────────────────────── */}
      {mobileSidebarOpen && (
        <>
          <div
            className="fixed inset-0 bg-black/50 z-30 lg:hidden"
            onClick={() => setMobileSidebarOpen(false)}
          />
          <div className="fixed inset-y-0 left-0 z-40 lg:hidden animate-sidebar-slide-in">
            <Sidebar
              collapsed={false}
              onToggle={() => setMobileSidebarOpen(false)}
            />
          </div>
        </>
      )}

      {/* ─── Main content ─────────────────────────────────────── */}
      <div
        className={cn(
          'flex flex-col flex-1 min-w-0 overflow-hidden transition-all duration-300',
        )}
      >
        <Header onMobileMenuToggle={() => setMobileSidebarOpen((v) => !v)} />

        <main className="flex-1 overflow-y-auto p-4 sm:p-6">
          <div className="animate-fade-in">
            <Outlet />
          </div>
        </main>
      </div>
    </div>
  )
}
