import type { Contract, ContractGroup, PaginatedResponse } from '@/types/models'
import { api } from './client'

export const contractApi = {
  getGroups: () =>
    api.get<ContractGroup[]>('/contracts/groups'),

  getAll: (params?: { page?: number; per_page?: number; employee_id?: number; status?: string }) =>
    api.get<PaginatedResponse<Contract>>('/contracts', params),
    
  getById: (id: number) => 
    api.get<Contract>(`/contracts/${id}`),
    
  create: (data: Partial<Contract>) => 
    api.post<Contract>('/contracts', data),
    
  update: (id: number, data: Partial<Contract>) => 
    api.put<Contract>(`/contracts/${id}`, data),
    
  delete: (id: number) => 
    api.delete<{ message: string }>(`/contracts/${id}`)
}
