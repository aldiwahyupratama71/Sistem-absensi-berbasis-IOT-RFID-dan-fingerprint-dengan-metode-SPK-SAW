import { zodResolver } from '@hookform/resolvers/zod'
import { BarChart3, Eye, EyeOff, Loader2, Lock, Mail } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { useNavigate } from 'react-router-dom'
import { z } from 'zod'

import { useAuth } from '@/hooks/useAuth'
import { cn } from '@/lib/utils'

const loginSchema = z.object({
  email: z.string().min(1, 'Email wajib diisi').email('Format email tidak valid'),
  password: z.string().min(1, 'Password wajib diisi'),
})

type LoginFormValues = z.infer<typeof loginSchema>

export default function LoginPage() {
  const navigate = useNavigate()
  const { login, isAuthenticated, isInitializing, isLoading, validationErrors, clearValidationErrors } = useAuth()
  const [showPassword, setShowPassword] = useState(false)
  const [serverError, setServerError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
  })

  // If already authenticated (and not still initializing), redirect to dashboard
  // This handles the case where user navigates to /login while already logged in
  useEffect(() => {
    if (!isInitializing && isAuthenticated) {
      navigate('/dashboard', { replace: true })
    }
  }, [isAuthenticated, isInitializing, navigate])

  // Map 422 validation errors from server to form fields
  useEffect(() => {
    if (validationErrors) {
      Object.entries(validationErrors).forEach(([field, messages]) => {
        setError(field as keyof LoginFormValues, {
          type: 'server',
          message: messages[0],
        })
      })
      clearValidationErrors()
    }
  }, [validationErrors, setError, clearValidationErrors])

  const onSubmit = async (data: LoginFormValues) => {
    setServerError(null)
    try {
      await login(data)
      navigate('/dashboard', { replace: true })
    } catch {
      // 422 errors are handled via validationErrors
      // Other errors (network, 500) show a generic message
      setServerError('Terjadi kesalahan. Silakan coba lagi.')
    }
  }

  const isMock = import.meta.env.VITE_USE_MOCK === 'true'

  return (
    <div className="min-h-screen flex items-center justify-center bg-background p-4">
      {/* Background decoration */}
      <div className="absolute inset-0 overflow-hidden pointer-events-none">
        <div className="absolute -top-40 -right-40 w-96 h-96 bg-primary/5 rounded-full blur-3xl" />
        <div className="absolute -bottom-40 -left-40 w-96 h-96 bg-primary/5 rounded-full blur-3xl" />
      </div>

      <div className="relative w-full max-w-md">
        {/* Card */}
        <div className="bg-card border border-border rounded-2xl shadow-xl p-8 animate-fade-in">
          {/* Logo */}
          <div className="flex flex-col items-center mb-8">
            <div className="w-14 h-14 rounded-2xl bg-primary flex items-center justify-center mb-4 shadow-lg shadow-primary/30">
              <BarChart3 size={28} className="text-white" />
            </div>
            <h1 className="text-2xl font-bold text-foreground">SPK Absensi</h1>
            <p className="text-muted-foreground text-sm mt-1">Masuk ke akun Anda</p>
          </div>

          {/* Mock notice */}
          {isMock && (
            <div className="mb-5 px-4 py-3 rounded-xl bg-blue-50 border border-blue-200 text-blue-700 text-xs">
              <strong>Mode Demo aktif.</strong> Gunakan{' '}
              <code className="font-mono bg-blue-100 px-1 rounded">admin@spk.test</code> /{' '}
              <code className="font-mono bg-blue-100 px-1 rounded">password</code>
            </div>
          )}

          {/* Server error */}
          {serverError && !Object.keys(errors).length && (
            <div className="mb-5 px-4 py-3 rounded-xl bg-destructive/10 border border-destructive/20 text-destructive text-sm">
              {serverError}
            </div>
          )}

          {/* Form */}
          <form id="login-form" onSubmit={handleSubmit(onSubmit)} noValidate className="space-y-5">
            {/* Email */}
            <div className="space-y-1.5">
              <label htmlFor="email" className="block text-sm font-medium">
                Email
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                  <Mail size={16} className="text-muted-foreground" />
                </div>
                <input
                  id="email"
                  type="email"
                  autoComplete="email"
                  placeholder="admin@spk.test"
                  {...register('email')}
                  className={cn(
                    'w-full pl-10 pr-4 py-2.5 rounded-xl border bg-background text-sm',
                    'focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary',
                    'transition-colors placeholder:text-muted-foreground/50',
                    errors.email
                      ? 'border-destructive focus:ring-destructive/30 focus:border-destructive'
                      : 'border-border',
                  )}
                />
              </div>
              {errors.email && (
                <p className="text-destructive text-xs mt-1">{errors.email.message}</p>
              )}
            </div>

            {/* Password */}
            <div className="space-y-1.5">
              <label htmlFor="password" className="block text-sm font-medium">
                Password
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                  <Lock size={16} className="text-muted-foreground" />
                </div>
                <input
                  id="password"
                  type={showPassword ? 'text' : 'password'}
                  autoComplete="current-password"
                  placeholder="••••••••"
                  {...register('password')}
                  className={cn(
                    'w-full pl-10 pr-10 py-2.5 rounded-xl border bg-background text-sm',
                    'focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary',
                    'transition-colors placeholder:text-muted-foreground/50',
                    errors.password
                      ? 'border-destructive focus:ring-destructive/30 focus:border-destructive'
                      : 'border-border',
                  )}
                />
                <button
                  type="button"
                  id="toggle-password"
                  onClick={() => setShowPassword((v) => !v)}
                  className="absolute inset-y-0 right-3 flex items-center text-muted-foreground hover:text-foreground transition-colors"
                >
                  {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                </button>
              </div>
              {errors.password && (
                <p className="text-destructive text-xs mt-1">{errors.password.message}</p>
              )}
            </div>

            {/* Submit */}
            <button
              id="login-submit"
              type="submit"
              disabled={isLoading}
              className={cn(
                'w-full py-2.5 px-4 rounded-xl font-medium text-sm transition-all duration-150',
                'bg-primary text-primary-foreground hover:bg-primary/90 active:scale-[0.98]',
                'focus:outline-none focus:ring-2 focus:ring-primary/30',
                'disabled:opacity-60 disabled:cursor-not-allowed',
                'flex items-center justify-center gap-2 shadow-md shadow-primary/20',
              )}
            >
              {isLoading && <Loader2 size={16} className="animate-spin" />}
              {isLoading ? 'Memproses...' : 'Masuk'}
            </button>
          </form>
        </div>

        <p className="text-center text-muted-foreground text-xs mt-6">
          © {new Date().getFullYear()} SPK Absensi — Sistem Pendukung Keputusan
        </p>
      </div>
    </div>
  )
}
