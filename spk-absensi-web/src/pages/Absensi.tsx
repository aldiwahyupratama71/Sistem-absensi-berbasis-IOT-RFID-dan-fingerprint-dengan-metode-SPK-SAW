import { useState, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useSearchParams } from 'react-router-dom'
import { 
  CalendarDays, Filter, RefreshCcw, Search, ChevronLeft, ChevronRight, X, Loader2, AlertCircle
} from 'lucide-react'

import { attendanceApi } from '@/api/attendance'
import { employeeApi } from '@/api/employees'
import { cn, formatDate } from '@/lib/utils'
import type { Attendance, AbsenceReason } from '@/types/models'

export default function AbsensiPage() {
  const queryClient = useQueryClient()
  const [searchParams, setSearchParams] = useSearchParams()
  
  // URL Params
  const page = Number(searchParams.get('page')) || 1
  const perPage = Number(searchParams.get('per_page')) || 15
  const employeeId = searchParams.get('employee_id') || ''
  const statusFilter = searchParams.get('status') || ''
  const methodFilter = searchParams.get('method') || ''
  const startDate = searchParams.get('start_date') || ''
  const endDate = searchParams.get('end_date') || ''

  // Helper to apply filter immediately
  const updateFilter = (key: string, value: string) => {
    setSearchParams(prev => {
      prev.set('page', '1')
      if (value) prev.set(key, value)
      else prev.delete(key)
      return prev
    })
  }

  // Reason Modal State
  const [reasonModal, setReasonModal] = useState<{ isOpen: boolean, attendance: Attendance | null }>({ isOpen: false, attendance: null })

  // ─── Queries ─────────────────────────────────────────────────────────────
  
  const { data: employeesData } = useQuery({
    queryKey: ['employees', 'all'],
    queryFn: () => employeeApi.getAll({ page: 1, per_page: 100 }) // Load up to 100 employees for the dropdown
  })

  const { data: attendanceData, isLoading } = useQuery({
    queryKey: ['attendance', page, perPage, employeeId, statusFilter, methodFilter, startDate, endDate],
    queryFn: () => attendanceApi.getAll({ 
      page,
      per_page: perPage,
      employee_id: employeeId,
      status: statusFilter,
      metode: methodFilter,
      date_from: startDate,
      date_to: endDate
    })
  })

  // ─── Handlers ────────────────────────────────────────────────────────────
  
  const handleResetFilter = () => {
    setSearchParams(new URLSearchParams())
  }

  const handlePageChange = (newPage: number) => {
    setSearchParams(prev => {
      prev.set('page', String(newPage))
      return prev
    })
  }

  const hasActiveFilters = employeeId || statusFilter || methodFilter || startDate || endDate

  // ─── Render ──────────────────────────────────────────────────────────────
  return (
    <div className="space-y-6 pb-8">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-foreground">Data Absensi</h1>
        <p className="text-sm text-muted-foreground">Monitor riwayat kehadiran, keterlambatan, dan ketidakhadiran karyawan.</p>
      </div>

      {/* Filters Toolbar */}
      <div className="bg-card border border-border rounded-2xl p-4 shadow-sm">
        <div className="flex flex-col xl:flex-row gap-4 items-end">
          <div className="flex-1 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 w-full">
            
            <div className="space-y-1.5">
              <label className="text-xs font-medium text-muted-foreground">Karyawan</label>
              <select 
                value={employeeId} 
                onChange={e => updateFilter('employee_id', e.target.value)}
                className="w-full px-3 py-2 bg-background border border-border rounded-lg text-sm"
              >
                <option value="">Semua Karyawan</option>
                {employeesData?.data.map(e => (
                  <option key={e.id} value={e.id}>{e.nama} - {e.nip}</option>
                ))}
              </select>
            </div>

            <div className="space-y-1.5">
              <label className="text-xs font-medium text-muted-foreground">Status</label>
              <select 
                value={statusFilter} 
                onChange={e => updateFilter('status', e.target.value)}
                className="w-full px-3 py-2 bg-background border border-border rounded-lg text-sm"
              >
                <option value="">Semua Status</option>
                <option value="HADIR">Hadir</option>
                <option value="TERLAMBAT">Terlambat</option>
                <option value="ABSENT">Tidak Hadir</option>
              </select>
            </div>

            <div className="space-y-1.5">
              <label className="text-xs font-medium text-muted-foreground">Metode</label>
              <select 
                value={methodFilter} 
                onChange={e => updateFilter('method', e.target.value)}
                className="w-full px-3 py-2 bg-background border border-border rounded-lg text-sm"
              >
                <option value="">Semua Metode</option>
                <option value="FINGERPRINT">Fingerprint</option>
                <option value="RFID">RFID Card</option>
              </select>
            </div>

            <div className="space-y-1.5">
              <label className="text-xs font-medium text-muted-foreground">Mulai Tanggal</label>
              <input 
                type="date" 
                value={startDate}
                onChange={e => updateFilter('start_date', e.target.value)}
                className="w-full px-3 py-2 bg-background border border-border rounded-lg text-sm"
              />
            </div>

            <div className="space-y-1.5">
              <label className="text-xs font-medium text-muted-foreground">Sampai Tanggal</label>
              <input 
                type="date" 
                value={endDate}
                onChange={e => updateFilter('end_date', e.target.value)}
                className="w-full px-3 py-2 bg-background border border-border rounded-lg text-sm"
              />
            </div>

          </div>
          
          <div className="flex gap-2 shrink-0">
            {hasActiveFilters && (
              <button 
                type="button" 
                onClick={handleResetFilter}
                className="px-4 py-2 text-sm font-medium border border-border rounded-lg hover:bg-muted transition-colors flex items-center gap-2"
              >
                <RefreshCcw size={16} /> Reset
              </button>
            )}
          </div>
        </div>
      </div>

      {/* Table */}
      <div className="bg-card border border-border rounded-2xl shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm text-left">
            <thead className="bg-muted/50 text-muted-foreground uppercase text-xs">
              <tr>
                <th className="px-6 py-4 font-semibold">Tanggal</th>
                <th className="px-6 py-4 font-semibold">Karyawan</th>
                <th className="px-6 py-4 font-semibold text-center">Metode</th>
                <th className="px-6 py-4 font-semibold">Jam Masuk</th>
                <th className="px-6 py-4 font-semibold text-center">Status</th>
                <th className="px-6 py-4 font-semibold">Keterlambatan</th>
                <th className="px-6 py-4 font-semibold">Alasan Ketidakhadiran</th>
                <th className="px-6 py-4 font-semibold text-right">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {isLoading ? (
                <tr>
                  <td colSpan={8} className="px-6 py-12 text-center text-muted-foreground">
                    <Loader2 size={24} className="animate-spin mx-auto mb-2" />
                    Memuat data absensi...
                  </td>
                </tr>
              ) : attendanceData?.data.length === 0 ? (
                <tr>
                  <td colSpan={8} className="px-6 py-12 text-center text-muted-foreground">
                    <CalendarDays size={32} className="mx-auto mb-2 opacity-20" />
                    Tidak ada data absensi yang ditemukan.
                  </td>
                </tr>
              ) : (
                attendanceData?.data.map((att) => (
                  <tr key={att.id} className="hover:bg-muted/30 transition-colors">
                    <td className="px-6 py-4 whitespace-nowrap">
                      {formatDate(att.tanggal)}
                    </td>
                    <td className="px-6 py-4">
                      <p className="font-semibold text-foreground">{att.employee?.nama}</p>
                      <p className="text-[11px] text-muted-foreground">{att.employee?.departemen}</p>
                    </td>
                    <td className="px-6 py-4 text-center">
                      {att.metode ? (
                        <span className="inline-flex px-1.5 py-0.5 text-[10px] font-medium bg-primary/10 text-primary rounded-md uppercase">
                          {att.metode === 'FINGERPRINT' ? 'FINGER' : att.metode}
                        </span>
                      ) : '-'}
                    </td>
                    <td className="px-6 py-4 font-mono text-sm">
                      {att.jam_masuk ? att.jam_masuk.slice(0, 5) : '--:--'}
                    </td>
                    <td className="px-6 py-4 text-center">
                      <StatusBadge status={att.status} />
                    </td>
                    <td className="px-6 py-4">
                      {att.menit_terlambat > 0 ? (
                        <span className="text-amber-600 font-medium text-xs">{att.menit_terlambat} menit</span>
                      ) : '-'}
                    </td>
                    <td className="px-6 py-4">
                      {att.status === 'ABSENT' ? (
                        <div className="flex items-center gap-2">
                          <span className={cn(
                            "px-1.5 py-0.5 text-[10px] font-bold rounded-md uppercase border",
                            att.absence_reason?.is_sah ? "bg-emerald-50 text-emerald-600 border-emerald-200" : "bg-rose-50 text-rose-600 border-rose-200"
                          )}>
                            {att.absence_reason?.is_sah ? 'Sah' : 'Tdk Sah'}
                          </span>
                          <span className="text-xs text-muted-foreground">{att.absence_reason?.nama || 'Belum diatur'}</span>
                        </div>
                      ) : '-'}
                    </td>
                    <td className="px-6 py-4 text-right">
                      {att.status === 'ABSENT' ? (
                        <button 
                          onClick={() => setReasonModal({ isOpen: true, attendance: att })}
                          className="text-xs font-medium text-blue-600 hover:text-blue-800 bg-blue-50 hover:bg-blue-100 px-2.5 py-1.5 rounded-lg transition-colors"
                        >
                          Set Alasan
                        </button>
                      ) : (
                        <span className="text-muted-foreground text-xs italic">-</span>
                      )}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
        
        {/* ── Pagination ── */}
        {attendanceData?.meta && (
          <div className="px-6 py-4 border-t border-border flex flex-col sm:flex-row items-center justify-between gap-3">
            {/* Info entri + per_page */}
            <div className="flex items-center gap-3 text-sm text-muted-foreground">
              <span>
                {attendanceData.meta.total === 0
                  ? 'Tidak ada data'
                  : `Menampilkan ${attendanceData.meta.from ?? 0}–${attendanceData.meta.to ?? 0} dari ${attendanceData.meta.total} entri`}
              </span>
              <select
                value={searchParams.get('per_page') || '15'}
                onChange={e => {
                  setSearchParams(prev => {
                    prev.set('per_page', e.target.value)
                    prev.set('page', '1')
                    return prev
                  })
                }}
                className="px-2 py-1 text-xs bg-background border border-border rounded-lg"
              >
                {[10, 15, 25, 50].map(n => (
                  <option key={n} value={n}>{n} / halaman</option>
                ))}
              </select>
            </div>

            {/* Page buttons — selalu tampil jika ada data */}
            {attendanceData.meta.total > 0 && (
              <div className="flex items-center gap-1">
                {/* Prev */}
                <button
                  onClick={() => handlePageChange(page - 1)}
                  disabled={page <= 1}
                  className="flex items-center gap-1 px-2.5 py-1.5 text-sm border border-border rounded-lg hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                >
                  <ChevronLeft size={14} /> Prev
                </button>

                {/* Page numbers */}
                {(() => {
                  const last = attendanceData.meta!.last_page
                  const pages: (number | 'ellipsis')[] = []
                  if (last <= 7) {
                    for (let i = 1; i <= last; i++) pages.push(i)
                  } else {
                    pages.push(1)
                    if (page > 3) pages.push('ellipsis')
                    const start = Math.max(2, page - 1)
                    const end = Math.min(last - 1, page + 1)
                    for (let i = start; i <= end; i++) pages.push(i)
                    if (page < last - 2) pages.push('ellipsis')
                    pages.push(last)
                  }
                  return pages.map((p, idx) =>
                    p === 'ellipsis' ? (
                      <span key={`e-${idx}`} className="px-1.5 py-1.5 text-muted-foreground text-sm">…</span>
                    ) : (
                      <button
                        key={p}
                        onClick={() => handlePageChange(p)}
                        className={cn(
                          'min-w-[32px] h-8 px-2 text-sm rounded-lg border transition-colors',
                          p === page
                            ? 'bg-primary text-primary-foreground border-primary font-semibold'
                            : 'border-border hover:bg-muted'
                        )}
                      >
                        {p}
                      </button>
                    )
                  )
                })()}

                {/* Next */}
                <button
                  onClick={() => handlePageChange(page + 1)}
                  disabled={page >= (attendanceData.meta?.last_page ?? 1)}
                  className="flex items-center gap-1 px-2.5 py-1.5 text-sm border border-border rounded-lg hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                >
                  Next <ChevronRight size={14} />
                </button>
              </div>
            )}
          </div>
        )}

      </div>

      {/* Set Reason Modal */}
      {reasonModal.isOpen && reasonModal.attendance && (
        <SetReasonModal
          attendance={reasonModal.attendance}
          onClose={() => setReasonModal({ isOpen: false, attendance: null })}
          onSuccess={() => {
            setReasonModal({ isOpen: false, attendance: null })
            // Invalidate data absensi agar tabel diperbarui
            queryClient.invalidateQueries({ queryKey: ['attendance'] })
            // Invalidate semua panel transparency yang sedang atau pernah terbuka
            // agar saat dibuka kembali, bukti_absensi sudah mencerminkan alasan baru.
            // (Nilai SAW masih dari perhitungan terakhir; user perlu klik Hitung Kembali
            //  untuk recalculate nilai, tapi bukti visual sudah benar.)
            queryClient.invalidateQueries({ queryKey: ['transparency'] })
          }}
        />
      )}
    </div>
  )
}

// ─── Sub Components ────────────────────────────────────────────────────────

function StatusBadge({ status }: { status: Attendance['status'] }) {
  const styles = {
    HADIR: "bg-emerald-100 text-emerald-700 border-emerald-200",
    TERLAMBAT: "bg-amber-100 text-amber-700 border-amber-200",
    ABSENT: "bg-rose-100 text-rose-700 border-rose-200"
  }
  const labels = {
    HADIR: "Hadir",
    TERLAMBAT: "Terlambat",
    ABSENT: "Tidak Hadir"
  }
  return (
    <span className={cn("inline-flex px-2 py-0.5 text-xs font-semibold rounded-md border", styles[status])}>
      {labels[status]}
    </span>
  )
}

function SetReasonModal({ attendance, onClose, onSuccess }: { attendance: Attendance, onClose: () => void, onSuccess: () => void }) {
  const [selectedReason, setSelectedReason] = useState(attendance.absence_reason_id?.toString() || '')
  
  const { data: reasons, isLoading } = useQuery({
    queryKey: ['absence_reasons'],
    queryFn: attendanceApi.getReasons
  })

  const mutation = useMutation({
    mutationFn: (reasonId: number) => attendanceApi.setReason(attendance.id, reasonId),
    onSuccess
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!selectedReason) return
    mutation.mutate(Number(selectedReason))
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/50 animate-fade-in" onClick={onClose} />
      <div className="relative bg-card border border-border rounded-2xl w-full max-w-md shadow-xl animate-fade-in">
        <div className="flex items-center justify-between p-4 border-b border-border">
          <h3 className="font-semibold">Set Alasan Ketidakhadiran</h3>
          <button onClick={onClose} className="p-1 text-muted-foreground hover:bg-muted rounded-full"><X size={18} /></button>
        </div>
        
        <form onSubmit={handleSubmit} className="p-4">
          <div className="mb-4 text-sm bg-muted/50 p-3 rounded-lg border border-border/50">
            <p><span className="text-muted-foreground">Karyawan:</span> {attendance.employee?.nama}</p>
            <p><span className="text-muted-foreground">Tanggal:</span> {formatDate(attendance.tanggal)}</p>
          </div>

          <div className="space-y-2">
            <label className="text-sm font-medium">Pilih Alasan <span className="text-destructive">*</span></label>
            {isLoading ? (
              <div className="h-10 bg-muted animate-pulse rounded-lg"></div>
            ) : (
              <select 
                value={selectedReason} 
                onChange={e => setSelectedReason(e.target.value)}
                required
                className="w-full px-3 py-2 bg-background border border-border rounded-lg text-sm"
              >
                <option value="" disabled>-- Pilih Alasan --</option>
                {reasons?.map(r => (
                  <option key={r.id} value={r.id}>
                    {r.nama} ({r.is_sah ? 'Sah' : 'Tidak Sah'})
                  </option>
                ))}
              </select>
            )}
          </div>

          <div className="flex justify-end gap-3 pt-6">
            <button type="button" onClick={onClose} className="px-4 py-2 text-sm font-medium hover:bg-muted rounded-lg">Batal</button>
            <button 
              type="submit" 
              disabled={mutation.isPending || !selectedReason} 
              className="px-4 py-2 text-sm font-medium bg-primary text-primary-foreground rounded-lg hover:bg-primary/90 disabled:opacity-50 flex items-center gap-2"
            >
              {mutation.isPending && <Loader2 size={16} className="animate-spin" />}
              Simpan
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
