import type { Employee, PaginatedResponse, Fingerprint } from '@/types/models'
import { api } from './client'

export interface EmployeeCard {
  id: number
  uid_rfid: string
  status: 'aktif' | 'nonaktif'
  created_at: string
}

export interface FingerprintData {
  id: number
  finger_id: string | number
  iot_device_id: number
  device_nama: string
  created_at: string
}

export interface PurgeResult {
  message: string
  deleted: {
    absensi: number
    kontrak: number
    evaluasi: number
    kartu_rfid: number
    sidik_jari: number
  }
}

export const employeeApi = {
  getAll: (params?: { page?: number; search?: string; status_aktif?: boolean; per_page?: number }) => 
    api.get<PaginatedResponse<Employee>>('/employees', params),
    
  getById: (id: number) => 
    api.get<Employee>(`/employees/${id}`),
    
  create: (data: Partial<Employee>) => 
    api.post<Employee>('/employees', data),
    
  update: (id: number, data: Partial<Employee>) => 
    api.put<Employee>(`/employees/${id}`, data),
    
  delete: (id: number, force = false) => 
    api.delete<{ message: string } | PurgeResult>(`/employees/${id}${force ? '?force=true' : ''}`),

  getCards: (id: number) =>
    api.get<{ data: EmployeeCard[] }>(`/employees/${id}/cards`).then(res => res.data),
    
  addCard: (id: number, uid_rfid: string) =>
    api.post<{ message: string }>(`/employees/${id}/cards`, { uid_rfid }),
    
  toggleCardStatus: (employeeId: number, cardId: number) =>
    api.patch<{ message: string; data: EmployeeCard }>(`/employees/${employeeId}/cards/${cardId}/status`),

  getFingerprints: (id: number) =>
    api.get<{ data: Fingerprint[] }>(`/employees/${id}/fingerprints`).then(res => res.data),

  addFingerprint: (employeeId: number, data: { device_id: number; finger_id: string }) =>
    api.post<{ data: FingerprintData }>(`/employees/${employeeId}/fingerprints`, data),

  deleteFingerprint: (employeeId: number, fpId: number) =>
    api.delete<{ message: string }>(`/employees/${employeeId}/fingerprints/${fpId}`)
}
