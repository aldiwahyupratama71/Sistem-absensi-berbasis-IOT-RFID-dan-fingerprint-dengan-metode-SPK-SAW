import { useAuth } from '@/hooks/useAuth'
import { cn } from '@/lib/utils'
import { LogOut, Menu, User } from 'lucide-react'
import { useState } from 'react'

interface HeaderProps {
  onMobileMenuToggle: () => void
}

export default function Header({ onMobileMenuToggle }: HeaderProps) {
  const { user, logout, isLoading } = useAuth()
  const [showDropdown, setShowDropdown] = useState(false)

  const handleLogout = async () => {
    setShowDropdown(false)
    await logout()
  }

  const initials = user?.name
    ? user.name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2)
    : 'U'

  return (
    <header className="h-16 flex items-center justify-between px-4 sm:px-6 bg-card border-b border-border">
      {/* Mobile hamburger */}
      <button
        className="lg:hidden p-2 rounded-lg hover:bg-accent transition-colors"
        onClick={onMobileMenuToggle}
        aria-label="Toggle mobile menu"
      >
        <Menu size={20} />
      </button>

      {/* Page breadcrumb area — empty for now */}
      <div className="flex-1" />

      {/* User menu */}
      <div className="relative">
        <button
          id="user-menu-button"
          onClick={() => setShowDropdown((v) => !v)}
          className={cn(
            'flex items-center gap-3 px-3 py-2 rounded-lg',
            'hover:bg-accent transition-colors',
            showDropdown && 'bg-accent',
          )}
        >
          {/* Avatar */}
          <div className="w-8 h-8 rounded-full bg-primary flex items-center justify-center text-primary-foreground text-xs font-semibold">
            {initials}
          </div>
          {/* Name (hidden on small screens) */}
          <div className="hidden sm:block text-left">
            <p className="text-sm font-medium leading-tight">{user?.name ?? 'Pengguna'}</p>
            <p className="text-xs text-muted-foreground capitalize">{user?.role ?? ''}</p>
          </div>
        </button>

        {/* Dropdown */}
        {showDropdown && (
          <>
            {/* Backdrop */}
            <div
              className="fixed inset-0 z-10"
              onClick={() => setShowDropdown(false)}
            />
            <div className="absolute right-0 top-full mt-2 w-52 bg-card border border-border rounded-xl shadow-lg z-20 overflow-hidden animate-fade-in">
              <div className="px-4 py-3 border-b border-border">
                <p className="text-sm font-medium">{user?.name}</p>
                <p className="text-xs text-muted-foreground">{user?.email}</p>
              </div>
              <div className="p-1">
                <button
                  id="profile-menu-item"
                  className="flex items-center gap-2 w-full px-3 py-2 text-sm rounded-lg hover:bg-accent transition-colors"
                >
                  <User size={15} />
                  Profil Saya
                </button>
                <button
                  id="logout-button"
                  onClick={handleLogout}
                  disabled={isLoading}
                  className="flex items-center gap-2 w-full px-3 py-2 text-sm rounded-lg text-destructive hover:bg-destructive/10 transition-colors"
                >
                  <LogOut size={15} />
                  {isLoading ? 'Keluar...' : 'Keluar'}
                </button>
              </div>
            </div>
          </>
        )}
      </div>
    </header>
  )
}
