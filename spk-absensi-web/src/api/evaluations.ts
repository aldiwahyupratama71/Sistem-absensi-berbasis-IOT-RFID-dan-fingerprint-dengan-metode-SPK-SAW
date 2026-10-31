import type { EvaluationPeriod, EvaluationMember, SpkEvaluation, SpkCriteria, PaginatedResponse, TransparencyData, RecalculateResponse } from '@/types/models'
import { api } from './client'

export const spkApi = {
  getPeriods: (params?: { page?: number; per_page?: number; kontrak_mulai?: string; kontrak_selesai?: string }) =>
    api.get<PaginatedResponse<EvaluationPeriod>>('/evaluation-periods', params),
    
  createPeriod: (data: Partial<EvaluationPeriod>) =>
    api.post<EvaluationPeriod>('/evaluation-periods', data),
    
  deletePeriod: (periodId: number, params?: { force?: boolean }) =>
    api.delete<{ message: string }>(`/evaluation-periods/${periodId}${params?.force ? '?force=true' : ''}`),

  getPeriodMembers: (periodId: number) =>
    api.get<{ evaluation_period_id: number; kontrak_mulai: string; kontrak_selesai: string; total: number; data: EvaluationMember[] }>(`/evaluation-periods/${periodId}/members`),

  getCriteria: () => 
    api.get<SpkCriteria[]>('/criteria'),
    
  getEvaluations: (periodId: number, params?: { page?: number; search?: string; kategori?: string }) => 
    api.get<PaginatedResponse<SpkEvaluation>>(`/evaluation-periods/${periodId}/results`, params),
    
  generateEvaluation: (periodId: number) => 
    api.post<{ message: string; summary: { jumlah_karyawan: number; distribusi_kategori: Record<string, number>; warnings: string[] } }>(`/evaluation-periods/${periodId}/calculate`),

  getTransparency: (evalId: number) =>
    api.get<TransparencyData>(`/evaluations/${evalId}`),

  setDecision: (evalId: number, data: { decision: 'DIPERPANJANG' | 'TIDAK_DIPERPANJANG' | 'DITUNDA'; catatan?: string }) =>
    api.put<{ message: string; data: { decision: string; catatan: string | null; decided_at: string } }>(`/evaluations/${evalId}/decision`, data),

  recalculate: (evalId: number) =>
    api.post<RecalculateResponse>(`/evaluations/${evalId}/recalculate`),
}

