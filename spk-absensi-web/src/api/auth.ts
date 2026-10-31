import type { AuthResponse, AuthUser, LoginPayload } from '@/types/auth'
import axiosInstance from './axiosInstance'

export async function loginApi(payload: LoginPayload): Promise<AuthResponse> {
  const response = await axiosInstance.post<AuthResponse>('/auth/login', payload)
  return response.data
}

export async function logoutApi(): Promise<void> {
  await axiosInstance.post('/auth/logout')
}

export async function getMeApi(): Promise<AuthUser> {
  const response = await axiosInstance.get<AuthUser>('/auth/me')
  return response.data
}

// Alias used by AuthProvider bootstrap
export const meApi = getMeApi
