import React, { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react'
import { loginApi, logoutApi, meApi } from '@/api/auth'
import { getToken, removeToken, setToken } from '@/api/axiosInstance'
import type { AuthUser, LoginPayload, ValidationError } from '@/types/auth'
import { AxiosError } from 'axios'

interface AuthContextType {
  user: AuthUser | null
  token: string | null
  isAuthenticated: boolean
  isLoading: boolean
  isInitializing: boolean
  login: (payload: LoginPayload) => Promise<void>
  logout: () => Promise<void>
  validationErrors: Record<string, string[]> | null
  clearValidationErrors: () => void
}

const AuthContext = createContext<AuthContextType | null>(null)

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null)
  const [token, setTokenState] = useState<string | null>(getToken)
  const [isLoading, setIsLoading] = useState(false)
  const [isInitializing, setIsInitializing] = useState(true)
  const [validationErrors, setValidationErrors] = useState<Record<string, string[]> | null>(null)
  const initRan = useRef(false)

  // On mount: if we have a stored token, verify it via /api/auth/me
  // This prevents the white flash — we know for sure if the session is valid
  // before rendering any protected content
  useEffect(() => {
    if (initRan.current) return
    initRan.current = true

    const storedToken = getToken()
    if (!storedToken) {
      setIsInitializing(false)
      return
    }

    meApi()
      .then((userData) => {
        setUser(userData)
      })
      .catch(() => {
        // Token invalid/expired — clear it
        removeToken()
        setTokenState(null)
      })
      .finally(() => {
        setIsInitializing(false)
      })
  }, [])

  const login = useCallback(async (payload: LoginPayload) => {
    setIsLoading(true)
    setValidationErrors(null)
    try {
      const response = await loginApi(payload)
      setToken(response.token)
      setTokenState(response.token)
      setUser(response.user)
    } catch (err) {
      const axiosErr = err as AxiosError<ValidationError>
      if (axiosErr.response?.status === 422 && axiosErr.response.data?.errors) {
        setValidationErrors(axiosErr.response.data.errors)
      }
      throw err
    } finally {
      setIsLoading(false)
    }
  }, [])

  const logout = useCallback(async () => {
    setIsLoading(true)
    try {
      await logoutApi()
    } catch {
      // Even if the server call fails, we still clear local state
    } finally {
      removeToken()
      setTokenState(null)
      setUser(null)
      setIsLoading(false)
    }
  }, [])

  const clearValidationErrors = useCallback(() => setValidationErrors(null), [])

  const value = useMemo(
    () => ({
      user,
      token,
      isAuthenticated: !!token && !isInitializing,
      isLoading,
      isInitializing,
      login,
      logout,
      validationErrors,
      clearValidationErrors,
    }),
    [user, token, isLoading, isInitializing, login, logout, validationErrors, clearValidationErrors],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth(): AuthContextType {
  const ctx = useContext(AuthContext)
  if (!ctx) {
    throw new Error('useAuth must be used within <AuthProvider>')
  }
  return ctx
}
