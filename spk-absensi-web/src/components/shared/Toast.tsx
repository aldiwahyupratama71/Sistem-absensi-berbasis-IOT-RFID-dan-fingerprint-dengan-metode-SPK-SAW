import { createContext, useContext, useState, useCallback, useRef } from 'react'
import { CheckCircle2, XCircle, AlertTriangle, Info, X } from 'lucide-react'
import { cn } from '@/lib/utils'

// ─── Types ───────────────────────────────────────────────────────────────────
type ToastVariant = 'success' | 'error' | 'warning' | 'info'

interface Toast {
  id: number
  message: string
  variant: ToastVariant
}

interface ToastContextValue {
  toast: (message: string, variant?: ToastVariant) => void
}

// ─── Context ─────────────────────────────────────────────────────────────────
const ToastContext = createContext<ToastContextValue | null>(null)

// ─── Provider ────────────────────────────────────────────────────────────────
export function ToastProvider({ children }: { children: React.ReactNode }) {
  const [toasts, setToasts] = useState<Toast[]>([])
  const counterRef = useRef(0)

  const dismiss = useCallback((id: number) => {
    setToasts(prev => prev.filter(t => t.id !== id))
  }, [])

  const toast = useCallback((message: string, variant: ToastVariant = 'info') => {
    const id = ++counterRef.current
    setToasts(prev => [...prev, { id, message, variant }])
    setTimeout(() => dismiss(id), 4000)
  }, [dismiss])

  return (
    <ToastContext.Provider value={{ toast }}>
      {children}
      {/* Toast Container */}
      <div
        aria-live="polite"
        className="fixed bottom-4 right-4 z-[200] flex flex-col gap-2 w-full max-w-sm"
      >
        {toasts.map(t => (
          <ToastItem key={t.id} toast={t} onDismiss={dismiss} />
        ))}
      </div>
    </ToastContext.Provider>
  )
}

// ─── Toast Item ──────────────────────────────────────────────────────────────
const VARIANT_STYLES: Record<ToastVariant, { wrapper: string; icon: React.ReactNode }> = {
  success: {
    wrapper: 'bg-emerald-50 border-emerald-200 text-emerald-800',
    icon: <CheckCircle2 size={18} className="text-emerald-500 shrink-0" />,
  },
  error: {
    wrapper: 'bg-rose-50 border-rose-200 text-rose-800',
    icon: <XCircle size={18} className="text-rose-500 shrink-0" />,
  },
  warning: {
    wrapper: 'bg-amber-50 border-amber-200 text-amber-800',
    icon: <AlertTriangle size={18} className="text-amber-500 shrink-0" />,
  },
  info: {
    wrapper: 'bg-blue-50 border-blue-200 text-blue-800',
    icon: <Info size={18} className="text-blue-500 shrink-0" />,
  },
}

function ToastItem({ toast, onDismiss }: { toast: Toast; onDismiss: (id: number) => void }) {
  const { wrapper, icon } = VARIANT_STYLES[toast.variant]
  return (
    <div
      role="alert"
      className={cn(
        'flex items-start gap-3 px-4 py-3 rounded-xl border shadow-lg text-sm animate-fade-in',
        wrapper
      )}
    >
      {icon}
      <span className="flex-1 leading-snug">{toast.message}</span>
      <button
        onClick={() => onDismiss(toast.id)}
        className="p-0.5 rounded hover:opacity-70 transition-opacity shrink-0"
        aria-label="Tutup notifikasi"
      >
        <X size={14} />
      </button>
    </div>
  )
}

// ─── Hook ────────────────────────────────────────────────────────────────────
export function useToast() {
  const ctx = useContext(ToastContext)
  if (!ctx) throw new Error('useToast must be used within ToastProvider')
  return ctx.toast
}
