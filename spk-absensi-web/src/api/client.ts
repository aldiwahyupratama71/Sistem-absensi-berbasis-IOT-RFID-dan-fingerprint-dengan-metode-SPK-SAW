import type { PaginatedResponse } from '@/types/models'
import axiosInstance from './axiosInstance'

export const api = {
  get: <T>(url: string, params?: Record<string, unknown>) => axiosInstance.get<T>(url, { params }).then((res) => res.data),
  post: <T>(url: string, data?: unknown) => axiosInstance.post<T>(url, data).then((res) => res.data),
  put: <T>(url: string, data?: unknown) => axiosInstance.put<T>(url, data).then((res) => res.data),
  patch: <T>(url: string, data?: unknown) => axiosInstance.patch<T>(url, data).then((res) => res.data),
  delete: <T>(url: string) => axiosInstance.delete<T>(url).then((res) => res.data),
}
