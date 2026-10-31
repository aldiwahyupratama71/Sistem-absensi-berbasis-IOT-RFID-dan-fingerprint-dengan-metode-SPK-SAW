// Auth related types

export interface LoginPayload {
  email: string
  password: string
}

export interface AuthUser {
  id: number
  name: string
  email: string
  role: 'admin' | 'manager' | 'staff'
  avatar?: string
}

export interface AuthResponse {
  user: AuthUser
  token: string
  token_type: 'Bearer'
}

/**
 * Shape of 422 Validation Error returned by Laravel
 */
export interface ValidationError {
  message: string
  errors: Record<string, string[]>
}
