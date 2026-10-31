import type { Attendance, PaginatedResponse, AbsenceReason } from '@/types/models'
import { api } from './client'

export const attendanceApi = {
  getAll: (params?: { page?: number; per_page?: number; date_from?: string; date_to?: string; employee_id?: string; status?: string; metode?: string }) => 
    api.get<PaginatedResponse<Attendance>>('/attendance', params),

    
  getById: (id: number) => 
    api.get<Attendance>(`/attendance/${id}`), // Wait, backend doesn't have this, but mock does

  getReasons: () =>
    api.get<AbsenceReason[]>('/absence-reasons'),

  setReason: (id: number, reason_id: number) =>
    api.patch<{ message: string }>(`/attendance/${id}/reason`, { absence_reason_id: reason_id })
}
