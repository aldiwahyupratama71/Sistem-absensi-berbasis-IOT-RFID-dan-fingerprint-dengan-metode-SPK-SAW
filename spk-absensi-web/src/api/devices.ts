import type { IotDevice, PaginatedResponse } from '@/types/models'
import { api } from './client'

export const deviceApi = {
  getAll: (params?: { page?: number; search?: string }) => 
    api.get<PaginatedResponse<IotDevice>>('/devices', params),
    
  getById: (id: number) => 
    api.get<IotDevice>(`/devices/${id}`),
    
  create: (data: Partial<IotDevice>) => 
    api.post<{ data: IotDevice, api_key: string }>('/devices', data),
    
  update: (id: number, data: Partial<IotDevice>) => 
    api.put<{ message: string, data: IotDevice }>(`/devices/${id}`, data),
    
  delete: (id: number) => 
    api.delete<{ message: string }>(`/devices/${id}`),
    
  regenerateKey: (id: number) =>
    api.post<{ data: IotDevice, api_key: string }>(`/devices/${id}/regenerate-key`)
}
