import { api } from './client'
import type { Attendance, EvaluationPeriod } from '@/types/models'

export interface DashboardResponse {
  kartu: {
    total_karyawan_aktif: number
    tanggal_referensi: string
    tepat_waktu: number
    terlambat: number
    tidak_hadir: number
    absen_fingerprint: number
  }
  rekap_kehadiran: {
    tepat_waktu: number
    terlambat: number
    tidak_hadir: number
    total: number
  }
  rata_rata_kriteria: {
    C1: number
    C2: number
    C3: number
  } | null
  distribusi_kategori: {
    positif: { jumlah: number; persen: number }
    pertimbangan: { jumlah: number; persen: number }
    perhatian: { jumlah: number; persen: number }
    evaluasi_khusus: { jumlah: number; persen: number }
  }
  peringatan: {
    absen_tanpa_alasan: number
    perangkat_tidak_aktif: number
  }
  periode: EvaluationPeriod | null
}

export interface DashboardChartData {
  tanggal: string
  hadir: number
  terlambat: number
  tidak_hadir: number
}

export const dashboardApi = {
  getStats: (period_id?: number) => 
    api.get<DashboardResponse>('/dashboard', period_id ? { period_id } : undefined),
    
  getChart: (range: 7 | 30) => 
    api.get<DashboardChartData[]>('/dashboard/attendance-trend', { range }),
    
  getRecent: () => 
    api.get<Attendance[]>('/dashboard/recent-attendance', { limit: 10 }),
}
