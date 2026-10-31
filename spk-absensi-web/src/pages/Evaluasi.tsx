import { useState, useMemo } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import {
  Calculator, Info, Search, X, Loader2, AlertCircle,
  TrendingUp, AlertTriangle, CheckCircle2,
  BookOpen, ShieldCheck, ChevronDown, Calendar, ArrowRight,
  Filter, RefreshCw
} from 'lucide-react'
import { AxiosError } from 'axios'
import { useNavigate } from 'react-router-dom'

import { spkApi } from '@/api/evaluations'
import { cn, formatDate } from '@/lib/utils'
import { useToast } from '@/components/shared/Toast'
import type { TransparencyData, RecalculateResponse } from '@/types/models'

// ─── HRD Decision schema ─────────────────────────────────────────────────────
const decisionSchema = z.object({
  decision: z.enum(['DIPERPANJANG', 'TIDAK_DIPERPANJANG', 'DITUNDA'] as const, {
    message: 'Pilih salah satu keputusan'
  } as any),
  catatan: z.string().optional(),
})
type DecisionForm = z.infer<typeof decisionSchema>

// ─── Label kategori helper ────────────────────────────────────────────────────
const KATEGORI_LABEL: Record<string, string> = {
  positif:         'Positif',
  pertimbangan:    'Perlu Pertimbangan',
  perhatian:       'Perlu Perhatian',
  evaluasi_khusus: 'Evaluasi Khusus',
}

// ─── Main Component ──────────────────────────────────────────────────────────
export default function EvaluasiPage() {
  const queryClient = useQueryClient()
  const toast = useToast()

  // States
  const [selectedPeriodId, setSelectedPeriodId] = useState<number | ''>('')
  const [search, setSearch] = useState('')
  const [transparencyModal, setTransparencyModal] = useState<{ isOpen: boolean; evalId: number | null }>({ isOpen: false, evalId: null })
  const [confirmRecalcOpen, setConfirmRecalcOpen] = useState(false)

  // Peringatan dari calculate (warnings)
  const [calcWarnings, setCalcWarnings] = useState<string[]>([])

  // Data Queries
  const { data: periodsData } = useQuery({
    queryKey: ['periods', 'all'],
    queryFn: () => spkApi.getPeriods({ page: 1 })
  })
  const periods = periodsData?.data || []
  const selectedPeriod = periods.find(p => p.id === Number(selectedPeriodId))

  const { data: evalsData, isLoading: isEvalsLoading } = useQuery({
    queryKey: ['evaluations', selectedPeriodId],
    queryFn: () => spkApi.getEvaluations(Number(selectedPeriodId), { page: 1, per_page: 100 } as any),
    enabled: !!selectedPeriodId
  })

  // ─── Mutation: Hitung Evaluasi (grup) ────────────────────────────────────────
  // DIAGNOSA FIX: sebelumnya hanya invalidate ['evaluations'] dan ['periods'].
  // Panel transparency yang sedang terbuka (queryKey ['transparency', evalId]) tidak
  // pernah di-invalidate, sehingga data lama tetap ditampilkan sampai panel ditutup/dibuka ulang.
  // Fix: invalidate seluruh transparency cache + members setelah calculateGroup berhasil.
  const generateMutation = useMutation({
    mutationFn: () => spkApi.generateEvaluation(Number(selectedPeriodId)),
    onSuccess: (res) => {
      // Invalidate semua query terkait evaluasi dalam periode ini
      queryClient.invalidateQueries({ queryKey: ['evaluations', selectedPeriodId] })
      queryClient.invalidateQueries({ queryKey: ['periods', 'all'] })
      queryClient.invalidateQueries({ queryKey: ['members', selectedPeriodId] })
      // Invalidate SEMUA panel transparency yang sedang atau pernah terbuka
      // (queryKey prefix ['transparency'] mencakup semua evalId)
      queryClient.invalidateQueries({ queryKey: ['transparency'] })
      // Invalidate dashboard agar summary berubah
      queryClient.invalidateQueries({ queryKey: ['dashboard'] })

      toast('Evaluasi berhasil dihitung!', 'success')
      const warnings = res.summary?.warnings ?? []
      setCalcWarnings(warnings)
      setConfirmRecalcOpen(false)
    },
    onError: (err: AxiosError<{ message?: string }>) => {
      const msg = err.response?.data?.message ?? 'Gagal menghitung evaluasi.'
      toast(msg, 'error')
      setConfirmRecalcOpen(false)
    }
  })

  const handleCalculate = () => {
    if (!selectedPeriodId) return
    if (selectedPeriod?.status_evaluasi === 'sudah_dihitung' || selectedPeriod?.status_evaluasi === 'sebagian_dihitung') {
      setConfirmRecalcOpen(true)
    } else {
      generateMutation.mutate()
    }
  }

  // Derived Data
  const rawEvals = evalsData?.data ?? []
  const sortedEvals = [...rawEvals].sort(
    (a, b) => parseFloat(b.nilai_akhir) - parseFloat(a.nilai_akhir)
  )
  const filteredEvals = sortedEvals.filter(e =>
    e.nama.toLowerCase().includes(search.toLowerCase()) ||
    e.departemen.toLowerCase().includes(search.toLowerCase())
  )

  // Ringkasan 4 kategori
  const count = useMemo(() => {
    const c = { positif: 0, pertimbangan: 0, perhatian: 0, evaluasi_khusus: 0 }
    rawEvals.forEach(e => {
      if (e.kategori in c) c[e.kategori as keyof typeof c]++
    })
    return c
  }, [rawEvals])

  return (
    <div className="space-y-6 pb-8">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-foreground">Evaluasi SPK (SAW)</h1>
        <p className="text-sm text-muted-foreground">Hitung dan lihat hasil rekomendasi evaluasi karyawan berdasarkan grup kontrak.</p>
      </div>

      {/* Control Panel */}
      <div className="bg-card border border-border rounded-2xl p-5 shadow-sm flex flex-col md:flex-row items-end gap-4 justify-between">
        <div className="w-full md:w-1/2 space-y-1.5">
          <label className="text-xs font-medium text-muted-foreground">Pilih Periode Berdasarkan Grup Kontrak</label>
          <select
            value={selectedPeriodId}
            onChange={e => { setSelectedPeriodId(Number(e.target.value) || ''); setCalcWarnings([]) }}
            className="w-full px-3 py-2.5 bg-background border border-border rounded-xl text-sm"
          >
            <option value="">-- Pilih Grup --</option>
            {periods.map(p => {
              const groupLabel = `${formatDate(p.kontrak_mulai)} - ${formatDate(p.kontrak_selesai)} (${p.jumlah_anggota} orang)`
              const mark = p.status_evaluasi === 'sudah_dihitung' ? '✓' : p.status_evaluasi === 'sebagian_dihitung' ? '◒' : ''
              return (
                <option key={p.id} value={p.id}>
                  {groupLabel} {mark ? `[${mark}]` : ''}
                </option>
              )
            })}
          </select>
        </div>

        <button
          onClick={handleCalculate}
          disabled={!selectedPeriodId || generateMutation.isPending}
          className="w-full md:w-auto px-6 py-2.5 bg-primary text-primary-foreground font-medium rounded-xl hover:bg-primary/90 transition-colors disabled:opacity-50 flex items-center justify-center gap-2"
        >
          {generateMutation.isPending ? <Loader2 size={18} className="animate-spin" /> : <Calculator size={18} />}
          Hitung Evaluasi ({selectedPeriod?.jumlah_anggota ?? 0} Karyawan)
        </button>
      </div>

      {/* Warnings Banner */}
      {calcWarnings.length > 0 && (
        <div className="bg-amber-50 border border-amber-200 rounded-xl p-4 flex gap-3 animate-fade-in">
          <AlertTriangle size={20} className="text-amber-600 shrink-0 mt-0.5" />
          <div className="flex-1">
            <p className="text-sm font-semibold text-amber-800 mb-1">Perhatian dari Hasil Kalkulasi</p>
            <ul className="list-disc pl-4 space-y-0.5">
              {calcWarnings.map((w, i) => (
                <li key={i} className="text-xs text-amber-700">{w}</li>
              ))}
            </ul>
          </div>
          <button onClick={() => setCalcWarnings([])} className="ml-auto p-1 text-amber-600 hover:text-amber-800 shrink-0">
            <X size={16} />
          </button>
        </div>
      )}

      {!selectedPeriodId ? (
        <div className="h-64 flex flex-col items-center justify-center text-muted-foreground border-2 border-dashed border-border rounded-2xl">
          <Calculator size={48} className="mb-4 opacity-20" />
          <p>Silakan pilih grup/periode evaluasi terlebih dahulu</p>
        </div>
      ) : (
        <div className="space-y-6 animate-fade-in">

          {/* Summary stats */}
          {rawEvals.length > 0 && (
            <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
              <div className="bg-slate-50 border border-slate-200 rounded-xl p-4 flex flex-col items-center justify-center text-center">
                <p className="text-xs text-slate-500 font-bold uppercase mb-1">Terhitung</p>
                <p className="text-2xl font-black text-slate-700">{rawEvals.length} <span className="text-sm font-medium text-slate-500">/ {selectedPeriod?.jumlah_anggota}</span></p>
              </div>
              <div className="bg-emerald-50 border border-emerald-200 rounded-xl p-4 flex flex-col items-center justify-center text-center">
                <p className="text-xs text-emerald-600 font-bold uppercase mb-1">Positif</p>
                <p className="text-2xl font-black text-emerald-700">{count.positif}</p>
              </div>
              <div className="bg-amber-50 border border-amber-200 rounded-xl p-4 flex flex-col items-center justify-center text-center">
                <p className="text-xs text-amber-600 font-bold uppercase mb-1">Pertimbangan</p>
                <p className="text-2xl font-black text-amber-700">{count.pertimbangan}</p>
              </div>
              <div className="bg-orange-50 border border-orange-200 rounded-xl p-4 flex flex-col items-center justify-center text-center">
                <p className="text-xs text-orange-600 font-bold uppercase mb-1">Perhatian</p>
                <p className="text-2xl font-black text-orange-700">{count.perhatian}</p>
              </div>
              <div className="bg-rose-50 border border-rose-200 rounded-xl p-4 flex flex-col items-center justify-center text-center">
                <p className="text-xs text-rose-600 font-bold uppercase mb-1">Evaluasi Khusus</p>
                <p className="text-2xl font-black text-rose-700">{count.evaluasi_khusus}</p>
              </div>
            </div>
          )}

          {/* Results Table */}
          <div className="bg-card border border-border rounded-2xl shadow-sm overflow-hidden">
            <div className="p-4 border-b border-border flex flex-col sm:flex-row justify-between items-center gap-4">
              <h2 className="font-semibold text-lg">Peringkat Karyawan (Jendela: {formatDate(selectedPeriod?.tanggal_mulai ?? '')} - {formatDate(selectedPeriod?.tanggal_selesai ?? '')})</h2>
              <div className="relative w-full sm:w-64">
                <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                <input
                  type="text"
                  placeholder="Cari nama / departemen..."
                  value={search}
                  onChange={e => setSearch(e.target.value)}
                  className="w-full pl-9 pr-4 py-2 rounded-lg border border-border bg-background text-sm focus:outline-none focus:ring-2 focus:ring-primary/30"
                />
              </div>
            </div>

            <div className="overflow-x-auto">
              <table className="w-full text-sm text-left">
                <thead className="bg-muted/50 text-muted-foreground text-xs uppercase">
                  <tr>
                    <th className="px-6 py-4 font-semibold text-center w-16">Peringkat</th>
                    <th className="px-6 py-4 font-semibold">Karyawan</th>
                    <th className="px-6 py-4 font-semibold text-center">C1 (Kehadiran)</th>
                    <th className="px-6 py-4 font-semibold text-center">C2 (Ketepatan)</th>
                    <th className="px-6 py-4 font-semibold text-center">C3 (ID Card)</th>
                    <th className="px-6 py-4 font-semibold text-center">Nilai Akhir</th>
                    <th className="px-6 py-4 font-semibold">Kategori</th>
                    <th className="px-6 py-4 font-semibold text-right">Detail</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {isEvalsLoading ? (
                    <tr>
                      <td colSpan={8} className="px-6 py-8 text-center text-muted-foreground">
                        <Loader2 size={24} className="animate-spin mx-auto" />
                      </td>
                    </tr>
                  ) : filteredEvals.length === 0 ? (
                    <tr>
                      <td colSpan={8} className="px-6 py-12 text-center text-muted-foreground">
                        Belum ada data evaluasi. Klik <strong>Hitung Evaluasi</strong> untuk memulai.
                      </td>
                    </tr>
                  ) : (
                    filteredEvals.map((ev, i) => (
                      <tr key={ev.id} className="hover:bg-muted/30 transition-colors">
                        <td className="px-6 py-4 text-center">
                          <span className={cn(
                            "inline-flex items-center justify-center w-8 h-8 rounded-full font-bold text-sm",
                            i === 0 ? "bg-yellow-100 text-yellow-700" :
                            i === 1 ? "bg-slate-100 text-slate-600" :
                            i === 2 ? "bg-orange-100 text-orange-700" : "bg-transparent text-muted-foreground"
                          )}>
                            #{i + 1}
                          </span>
                        </td>
                        <td className="px-6 py-4">
                          <p className="font-semibold">{ev.nama}</p>
                          <p className="text-xs text-muted-foreground">{ev.departemen}</p>
                        </td>
                        <td className="px-6 py-4 text-center font-mono">
                          {ev.C1 != null ? parseFloat(ev.C1).toFixed(1) : '–'}
                        </td>
                        <td className="px-6 py-4 text-center font-mono">
                          {ev.C2 != null ? parseFloat(ev.C2).toFixed(1) : '–'}
                        </td>
                        <td className="px-6 py-4 text-center font-mono">
                          {ev.C3 != null ? parseFloat(ev.C3).toFixed(1) : '–'}
                        </td>
                        <td className="px-6 py-4 text-center">
                          <span className="font-bold text-lg text-primary">
                            {parseFloat(ev.nilai_akhir).toFixed(1)}%
                          </span>
                        </td>
                        <td className="px-6 py-4">
                          <CategoryBadge category={ev.kategori} />
                        </td>
                        <td className="px-6 py-4 text-right">
                          <button
                            onClick={() => setTransparencyModal({ isOpen: true, evalId: ev.id })}
                            className="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-blue-600 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors"
                          >
                            <TrendingUp size={14} /> Analisis
                          </button>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* Modal Konfirmasi Hitung Ulang (grup) */}
      {confirmRecalcOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="absolute inset-0 bg-black/50" onClick={() => setConfirmRecalcOpen(false)} />
          <div className="relative bg-card border border-border rounded-2xl w-full max-w-sm shadow-xl p-6 animate-fade-in">
            <div className="flex items-center gap-3 mb-3">
              <div className="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center shrink-0">
                <AlertTriangle size={20} className="text-amber-600" />
              </div>
              <h3 className="font-semibold text-lg">Hitung Ulang Evaluasi?</h3>
            </div>
            <p className="text-sm text-muted-foreground mb-6">
              Periode ini sudah memiliki hasil yang tersimpan. Menghitung ulang akan <strong>memperbarui nilai</strong> semua karyawan dalam grup. Lanjutkan?
            </p>
            <div className="flex justify-end gap-3">
              <button
                onClick={() => setConfirmRecalcOpen(false)}
                className="px-4 py-2 text-sm font-medium hover:bg-muted rounded-lg transition-colors"
              >
                Batal
              </button>
              <button
                onClick={() => generateMutation.mutate()}
                disabled={generateMutation.isPending}
                className="px-4 py-2 text-sm font-medium bg-amber-500 text-white rounded-lg hover:bg-amber-600 flex items-center gap-2 disabled:opacity-50"
              >
                {generateMutation.isPending && <Loader2 size={14} className="animate-spin" />}
                Ya, Hitung Ulang
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Transparency Panel */}
      {transparencyModal.isOpen && transparencyModal.evalId && selectedPeriod && (
        <TransparencyPanel
          evalId={transparencyModal.evalId}
          period={selectedPeriod}
          periodId={Number(selectedPeriodId)}
          onClose={() => setTransparencyModal({ isOpen: false, evalId: null })}
        />
      )}
    </div>
  )
}

function CategoryBadge({ category }: { category: string }) {
  const map: Record<string, { label: string; class: string }> = {
    'positif':        { label: 'Positif',              class: 'bg-emerald-100 text-emerald-700 border-emerald-200' },
    'pertimbangan':   { label: 'Perlu Pertimbangan',   class: 'bg-amber-100 text-amber-700 border-amber-200' },
    'perhatian':      { label: 'Perlu Perhatian',      class: 'bg-orange-100 text-orange-700 border-orange-200' },
    'evaluasi_khusus':{ label: 'Evaluasi Khusus',      class: 'bg-rose-100 text-rose-700 border-rose-200' },
  }
  const config = map[category] ?? { label: category, class: 'bg-muted text-muted-foreground border-border' }
  return (
    <span className={cn("inline-flex px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider rounded-md border", config.class)}>
      {config.label}
    </span>
  )
}

// ─── TransparencyPanel ────────────────────────────────────────────────────────
function TransparencyPanel({
  evalId, period, periodId, onClose
}: {
  evalId: number
  period: any
  periodId: number
  onClose: () => void
}) {
  const toast = useToast()
  const queryClient = useQueryClient()
  const navigate = useNavigate()

  // Panel selalu membaca data dari query — tidak ada salinan ke local state
  const { data, isLoading, isError } = useQuery({
    queryKey: ['transparency', evalId],
    queryFn: () => spkApi.getTransparency(evalId),
    // staleTime 0 agar setelah invalidate langsung refetch
    staleTime: 0,
  })

  // ─── Mutation: Hitung Kembali satu karyawan ───────────────────────────────
  const [hrdCategoryChanged, setHrdCategoryChanged] = useState(false)

  const recalcMutation = useMutation({
    mutationFn: () => spkApi.recalculate(evalId),
    onSuccess: (res: RecalculateResponse) => {
      const namaKaryawan = res.employee?.nama ?? 'Karyawan'
      const nilaiBaru  = parseFloat(res.nilai_akhir).toFixed(2)
      const nilaiLama  = parseFloat(res.sebelum.nilai_akhir).toFixed(2)
      const katBaru    = KATEGORI_LABEL[res.kategori] ?? res.kategori
      const katLama    = KATEGORI_LABEL[res.sebelum.kategori] ?? res.sebelum.kategori

      // Update panel langsung dari response (tanpa round-trip ke server)
      queryClient.setQueryData(['transparency', evalId], res)

      // Invalidate tabel evaluasi, members, dan dashboard
      queryClient.invalidateQueries({ queryKey: ['evaluations', periodId] })
      queryClient.invalidateQueries({ queryKey: ['members', periodId] })
      queryClient.invalidateQueries({ queryKey: ['periods', 'all'] })
      queryClient.invalidateQueries({ queryKey: ['dashboard'] })

      // Toast ringkasan
      if (nilaiLama === nilaiBaru) {
        toast(`Tidak ada perubahan, nilai tetap ${nilaiBaru}`, 'success')
      } else {
        toast(
          `${namaKaryawan}: ${nilaiLama} (${katLama}) → ${nilaiBaru} (${katBaru})`,
          'success'
        )
      }

      // Banner HRD warning: jika ada keputusan HRD dan kategori berubah
      const sudahAdaDecision = !!res.keputusan_hrd
      const kategoriChange   = res.sebelum.kategori !== res.kategori
      setHrdCategoryChanged(sudahAdaDecision && kategoriChange)
    },
    onError: (err: AxiosError<{ message?: string }>) => {
      const msg = err.response?.data?.message ?? 'Gagal menghitung ulang evaluasi.'
      toast(msg, 'error')
    }
  })

  // Filter bukti absensi
  const [filterKode, setFilterKode] = useState<string>('ALL')

  const buktiFiltered = useMemo(() => {
    if (!data?.bukti_absensi) return []
    if (filterKode === 'ALL') return data.bukti_absensi
    return data.bukti_absensi.filter(b => b.kriteria === filterKode)
  }, [data?.bukti_absensi, filterKode])

  const subtotalPoin = useMemo(() => {
    if (filterKode === 'ALL') return 0
    return buktiFiltered.reduce((sum, b) => sum + b.poin_penalti, 0)
  }, [buktiFiltered, filterKode])

  const currentNilaiMentah = useMemo(() => {
    if (filterKode === 'ALL') return null
    return data?.langkah_saw?.langkah?.find(l => l.kode === filterKode)?.nilai_mentah ?? 0
  }, [data, filterKode])

  return (
    <div className="fixed inset-0 z-50 flex justify-end">
      <div className="absolute inset-0 bg-black/50 animate-fade-in" onClick={onClose} />
      <div className="relative w-full max-w-4xl bg-background h-full shadow-2xl flex flex-col animate-sidebar-slide-in" style={{ transformOrigin: 'right' }}>

        {/* Header */}
        <div className="p-6 border-b border-border bg-card flex justify-between items-start shrink-0">
          <div className="flex-1">
            <h2 className="text-xl font-bold flex items-center gap-2">
              <TrendingUp className="text-blue-500" /> Analisis SPK (SAW)
            </h2>
            <p className="text-xs text-muted-foreground mt-1">Hitung ulang dari data absensi terbaru</p>
          </div>
          <div className="flex items-center gap-2 ml-4">
            {/* Tombol Hitung Kembali */}
            <button
              id="btn-hitung-kembali"
              onClick={() => recalcMutation.mutate()}
              disabled={recalcMutation.isPending || isLoading}
              className="flex items-center gap-2 px-4 py-2 text-sm font-semibold bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 disabled:opacity-50 transition-colors shadow-sm"
              title="Hitung ulang SAW dari data absensi terbaru"
            >
              {recalcMutation.isPending
                ? <Loader2 size={16} className="animate-spin" />
                : <RefreshCw size={16} />
              }
              Hitung Kembali
            </button>
            <button onClick={onClose} className="p-2 text-muted-foreground hover:bg-muted rounded-full">
              <X size={20} />
            </button>
          </div>
        </div>

        {/* Content */}
        <div className="p-6 flex-1 overflow-y-auto bg-muted/20 space-y-8">
          {/* Banner: nilai berubah setelah ada keputusan HRD */}
          {hrdCategoryChanged && (
            <div className="bg-amber-50 border border-amber-300 rounded-xl p-4 flex gap-3 animate-fade-in">
              <AlertTriangle size={20} className="text-amber-600 shrink-0 mt-0.5" />
              <div className="flex-1">
                <p className="text-sm font-semibold text-amber-800">Nilai berubah setelah keputusan HRD dibuat.</p>
                <p className="text-xs text-amber-700 mt-0.5">Tinjau kembali keputusan HRD. Keputusan yang tersimpan tidak diubah secara otomatis.</p>
              </div>
              <button onClick={() => setHrdCategoryChanged(false)} className="p-1 text-amber-600 hover:text-amber-800 shrink-0">
                <X size={14} />
              </button>
            </div>
          )}

          {isLoading ? (
            <div className="flex flex-col items-center justify-center h-48 text-muted-foreground">
              <Loader2 size={32} className="animate-spin mb-4" />
              <p>Menganalisis data...</p>
            </div>
          ) : isError || !data ? (
            <div className="text-center text-rose-500 p-8 border border-rose-200 rounded-xl bg-rose-50">
              <AlertCircle size={32} className="mx-auto mb-2" />
              <p>Gagal memuat detail transparansi.</p>
            </div>
          ) : (
            <div className="space-y-8 pb-10">

              {/* BAGIAN 1: Ringkasan */}
              <section className="bg-card border border-border p-6 rounded-2xl shadow-sm relative overflow-hidden">
                <div className="absolute top-0 right-0 p-4">
                   <CategoryBadge category={data.kategori} />
                </div>
                <div className="flex flex-col md:flex-row gap-6 items-start md:items-center">
                  <div className="flex-1">
                    <p className="text-sm font-semibold text-primary uppercase tracking-wider mb-1">Karyawan</p>
                    <h3 className="text-2xl font-bold text-foreground">{data.employee?.nama}</h3>
                    <p className="text-sm text-muted-foreground mt-1">{data.employee?.nip} • {data.employee?.departemen}</p>

                    <div className="flex gap-4 mt-4 text-xs">
                      <div className="bg-muted px-3 py-1.5 rounded-lg border border-border flex items-center gap-2">
                        <UsersIcon size={14} className="text-muted-foreground"/>
                        <span className="font-medium">Grup: {formatDate(period.kontrak_mulai)} - {formatDate(period.kontrak_selesai)}</span>
                      </div>
                      <div className="bg-indigo-50 text-indigo-700 px-3 py-1.5 rounded-lg border border-indigo-100 flex items-center gap-2">
                        <Calendar size={14} className="text-indigo-600"/>
                        <span className="font-medium">Jendela: {formatDate(period.tanggal_mulai)} - {formatDate(period.tanggal_selesai)}</span>
                      </div>
                    </div>
                  </div>

                  <div className="bg-primary/5 p-4 rounded-xl border border-primary/20 text-center min-w-[150px]">
                    <p className="text-xs font-semibold text-primary uppercase mb-1">Nilai Akhir</p>
                    <p className="text-4xl font-black text-primary">
                      {parseFloat(data.nilai_akhir).toFixed(2)}<span className="text-xl">%</span>
                    </p>
                  </div>
                </div>
              </section>

              {/* BAGIAN 2: Data Mentah dari Absensi */}
              <section>
                <div className="flex justify-between items-center mb-3">
                  <h3 className="font-bold text-foreground flex items-center gap-2 text-lg">
                    <span className="bg-primary text-primary-foreground w-6 h-6 rounded flex items-center justify-center text-sm">1</span>
                    Data Mentah Absensi
                  </h3>
                  <button
                    onClick={() => {
                      onClose();
                      navigate(`/absensi?employee_id=${data.employee.id}&date_from=${period.tanggal_mulai}&date_to=${period.tanggal_selesai}`);
                    }}
                    className="text-xs font-medium bg-white border border-border hover:bg-muted px-3 py-1.5 rounded-lg flex items-center gap-2 transition-colors"
                  >
                    Lihat di Data Absensi <ArrowRight size={14}/>
                  </button>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div className="bg-card border border-border rounded-xl p-4 shadow-sm">
                    <p className="font-semibold text-sm mb-3 flex items-center gap-1.5"><div className="w-2 h-2 rounded-full bg-emerald-500"></div> C1 (Kehadiran)</p>
                    <div className="space-y-2 text-xs">
                      <DataRow label="Absen tanpa keterangan" value={data.data_mentah.absent_tanpa_keterangan} unit="hari" danger={data.data_mentah.absent_tanpa_keterangan > 0} />
                      <DataRow label="Absen dengan alasan sah" value={data.data_mentah.absent_sah} unit="hari" />
                    </div>
                  </div>
                  <div className="bg-card border border-border rounded-xl p-4 shadow-sm">
                    <p className="font-semibold text-sm mb-3 flex items-center gap-1.5"><div className="w-2 h-2 rounded-full bg-amber-500"></div> C2 (Ketepatan Waktu)</p>
                    <div className="space-y-2 text-xs">
                      <DataRow label="Terlambat 1–10 menit" value={data.data_mentah.telat_1_10} unit="kali" danger={data.data_mentah.telat_1_10 > 0} />
                      <DataRow label="Terlambat > 10 menit" value={data.data_mentah.telat_lebih_10} unit="kali" danger={data.data_mentah.telat_lebih_10 > 0} />
                    </div>
                  </div>
                  <div className="bg-card border border-border rounded-xl p-4 shadow-sm">
                    <p className="font-semibold text-sm mb-3 flex items-center gap-1.5"><div className="w-2 h-2 rounded-full bg-blue-500"></div> C3 (ID Card)</p>
                    <div className="space-y-2 text-xs">
                      <DataRow label="Total Hari Hadir" value={data.data_mentah.total_hadir} unit="hari" />
                      <DataRow label="Absen via Fingerprint" value={data.data_mentah.pakai_fingerprint} unit="kali" danger={data.data_mentah.pakai_fingerprint > 0} />
                    </div>
                  </div>
                </div>
              </section>

              {/* BAGIAN 3: Langkah SAW */}
              <section>
                <h3 className="font-bold text-foreground mb-3 flex items-center gap-2 text-lg">
                  <span className="bg-primary text-primary-foreground w-6 h-6 rounded flex items-center justify-center text-sm">2</span>
                  Langkah Normalisasi SAW
                </h3>
                <div className="bg-card border border-border rounded-xl overflow-hidden shadow-sm">
                  <div className="overflow-x-auto">
                    <table className="w-full text-sm text-left whitespace-nowrap">
                      <thead className="bg-muted text-xs uppercase text-muted-foreground border-b border-border">
                        <tr>
                          <th className="px-4 py-3 font-semibold">Kriteria</th>
                          <th className="px-4 py-3 font-semibold text-center">① Nilai Mentah</th>
                          <th className="px-4 py-3 font-semibold text-center">② Normalisasi (÷100)</th>
                          <th className="px-4 py-3 font-semibold text-center">③ Bobot</th>
                          <th className="px-4 py-3 font-semibold text-right">④ Kontribusi</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-border">
                        {data.langkah_saw.langkah.map(step => (
                          <tr key={step.kode} className="hover:bg-muted/20">
                            <td className="px-4 py-3">
                              <p className="font-bold">{step.kode}</p>
                              <p className="text-xs text-muted-foreground">{step.nama}</p>
                            </td>
                            <td className="px-4 py-3 text-center">
                              <div className="font-mono font-bold text-base">{step.nilai_mentah.toFixed(2)}</div>
                              <div className="text-[10px] text-muted-foreground mt-1 bg-muted rounded p-1 max-w-[200px] whitespace-normal mx-auto leading-tight break-words border border-border">
                                {step.rumus_substitusi}
                              </div>
                              {step.dibatasi && (
                                <div className="text-[10px] text-rose-600 mt-1 font-semibold flex items-center justify-center gap-1">
                                  <AlertTriangle size={10}/> Nilai dibatasi minimal 0
                                </div>
                              )}
                            </td>
                            <td className="px-4 py-3 text-center font-mono text-blue-600 font-semibold text-base">
                              {step.normalisasi.toFixed(3)}
                            </td>
                            <td className="px-4 py-3 text-center font-mono text-amber-600 font-semibold text-base">
                              {step.bobot * 100}%
                            </td>
                            <td className="px-4 py-3 text-right font-mono font-black text-primary text-base">
                              {(step.kontribusi * 100).toFixed(2)}%
                            </td>
                          </tr>
                        ))}
                      </tbody>
                      <tfoot className="bg-primary/5 border-t-2 border-primary/20">
                        <tr>
                          <td colSpan={4} className="px-4 py-4 text-right font-bold text-primary uppercase text-xs tracking-wider">
                            Total Kontribusi (Nilai Akhir):
                          </td>
                          <td className="px-4 py-4 text-right">
                            <span className="font-black text-2xl text-primary">{data.langkah_saw.nilai_akhir_persen.toFixed(2)}%</span>
                          </td>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                </div>
              </section>

              {/* BAGIAN 4: Bukti Absensi */}
              <section>
                <h3 className="font-bold text-foreground mb-3 flex items-center gap-2 text-lg">
                  <span className="bg-primary text-primary-foreground w-6 h-6 rounded flex items-center justify-center text-sm">3</span>
                  Bukti Insiden Absensi
                </h3>

                <div className="bg-card border border-border rounded-xl shadow-sm overflow-hidden flex flex-col">
                  <div className="p-4 border-b border-border flex items-center gap-3 overflow-x-auto bg-muted/30">
                    <Filter size={16} className="text-muted-foreground shrink-0"/>
                    <span className="text-sm font-semibold shrink-0">Filter Kriteria:</span>
                    <div className="flex gap-2">
                      {['ALL', 'C1', 'C2', 'C3'].map(k => (
                        <button
                          key={k}
                          onClick={() => setFilterKode(k)}
                          className={cn(
                            "px-3 py-1 rounded-full text-xs font-bold transition-colors border shrink-0",
                            filterKode === k
                              ? "bg-primary text-primary-foreground border-primary"
                              : "bg-white text-muted-foreground border-border hover:bg-muted"
                          )}
                        >
                          {k === 'ALL' ? 'Semua Insiden' : k}
                        </button>
                      ))}
                    </div>
                  </div>

                  {buktiFiltered.length === 0 ? (
                    <div className="p-12 flex flex-col items-center justify-center text-muted-foreground">
                      <CheckCircle2 size={40} className="text-emerald-400 mb-3"/>
                      <p className="font-medium text-lg text-emerald-700">Tidak ada penalti {filterKode !== 'ALL' ? `untuk kriteria ${filterKode}` : ''}</p>
                      {filterKode !== 'ALL' && <p className="text-sm mt-1">Nilai Mentah {filterKode} = 100</p>}
                    </div>
                  ) : (
                    <div className="overflow-x-auto">
                      <table className="w-full text-sm text-left">
                        <thead className="bg-muted/50 text-xs uppercase text-muted-foreground">
                          <tr>
                            <th className="px-4 py-3 font-semibold">Tanggal</th>
                            <th className="px-4 py-3 font-semibold">Hari</th>
                            <th className="px-4 py-3 font-semibold">Status / Jam</th>
                            <th className="px-4 py-3 font-semibold">Metode</th>
                            <th className="px-4 py-3 font-semibold">Jenis</th>
                            <th className="px-4 py-3 font-semibold text-center">Kriteria</th>
                            <th className="px-4 py-3 font-semibold text-right">Poin</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                          {buktiFiltered.map((b, i) => {
                            // ABSEN_SAH ditampilkan dengan style berbeda (hijau, poin 0)
                            const isSah = b.jenis === 'ABSEN_SAH'
                            return (
                              <tr key={i} className={cn("hover:bg-muted/20", isSah && "bg-emerald-50/40")}>
                                <td className="px-4 py-3 font-mono font-medium">{b.tanggal}</td>
                                <td className="px-4 py-3">{b.hari}</td>
                                <td className="px-4 py-3">
                                  <span className={cn(
                                    "font-bold text-xs px-2 py-0.5 rounded",
                                    b.status === 'ABSENT'
                                      ? (isSah ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700')
                                      : 'bg-orange-100 text-orange-700'
                                  )}>
                                    {b.status}
                                  </span>
                                  {b.jam_masuk && <span className="block mt-1 text-xs font-mono">{b.jam_masuk} {b.menit_terlambat > 0 && <span className="text-rose-600">(+{b.menit_terlambat}m)</span>}</span>}
                                </td>
                                <td className="px-4 py-3 font-mono text-xs">{b.metode || '-'}</td>
                                <td className="px-4 py-3">
                                  <span className={cn(
                                    "font-mono text-xs px-2 py-1 rounded border",
                                    isSah
                                      ? "bg-emerald-50 text-emerald-700 border-emerald-200"
                                      : "bg-muted text-foreground border-border"
                                  )}>
                                    {isSah ? 'Absen Sah' : b.jenis}
                                  </span>
                                </td>
                                <td className="px-4 py-3 text-center font-bold">{b.kriteria}</td>
                                <td className={cn(
                                  "px-4 py-3 text-right font-mono font-bold",
                                  isSah ? "text-emerald-600" : "text-rose-600"
                                )}>
                                  {isSah ? '0' : `-${b.poin_penalti % 1 === 0 ? b.poin_penalti : b.poin_penalti.toFixed(4)}`}
                                </td>
                              </tr>
                            )
                          })}
                        </tbody>
                        {filterKode !== 'ALL' && (
                          <tfoot className="bg-rose-50 border-t border-rose-200">
                            <tr>
                              <td colSpan={6} className="px-4 py-3 text-right font-bold text-rose-800 text-xs uppercase">Total Poin Penalti {filterKode}:</td>
                              <td className="px-4 py-3 text-right font-black text-rose-700 text-base">-{subtotalPoin % 1 === 0 ? subtotalPoin : subtotalPoin.toFixed(4)}</td>
                            </tr>
                            <tr>
                              <td colSpan={7} className="px-4 py-2 text-right text-xs bg-white border-t border-rose-100 text-muted-foreground">
                                Rumus Dasar: 100 - {subtotalPoin % 1 === 0 ? subtotalPoin : subtotalPoin.toFixed(2)} = <strong className="text-foreground text-sm">{currentNilaiMentah?.toFixed(2)}</strong> (Sebelum batas min 0)
                              </td>
                            </tr>
                          </tfoot>
                        )}
                      </table>
                    </div>
                  )}
                </div>
              </section>

              {/* BAGIAN 5: Keputusan HRD */}
              <section>
                <h3 className="font-bold text-foreground mb-3 flex items-center gap-2 text-lg">
                  <span className="bg-primary text-primary-foreground w-6 h-6 rounded flex items-center justify-center text-sm">4</span>
                  Keputusan HRD
                </h3>
                <HrdDecisionSection
                  evalId={evalId}
                  existingDecision={data.keputusan_hrd}
                />
              </section>

            </div>
          )}
        </div>
      </div>
    </div>
  )
}

function DataRow({ label, value, unit, danger }: { label: string; value: number; unit: string; danger?: boolean }) {
  return (
    <div className="flex justify-between items-center bg-white p-2.5 rounded-lg border border-border">
      <span className="text-muted-foreground">{label}</span>
      <span className={cn("font-bold font-mono text-base", danger ? "text-rose-600" : "text-foreground")}>
        {value} <span className="font-normal text-muted-foreground text-xs">{unit}</span>
      </span>
    </div>
  )
}

function UsersIcon({ size, className }: { size?: number; className?: string }) {
  return (
    <svg
      xmlns="http://www.w3.org/2000/svg"
      width={size ?? 24}
      height={size ?? 24}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      className={className}
    >
      <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
      <circle cx="9" cy="7" r="4"/>
      <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
      <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
    </svg>
  )
}

// ─── HrdDecisionSection ───────────────────────────────────────────────────────
const DECISION_LABELS: Record<string, string> = {
  DIPERPANJANG: 'Diperpanjang',
  TIDAK_DIPERPANJANG: 'Tidak Diperpanjang',
  DITUNDA: 'Ditunda',
}

function HrdDecisionSection({ evalId, existingDecision }: { evalId: number; existingDecision: TransparencyData['keputusan_hrd'] }) {
  const toast = useToast()
  const queryClient = useQueryClient()
  const [isExpanded, setIsExpanded] = useState(true)

  const { register, handleSubmit, formState: { errors } } = useForm<DecisionForm>({
    resolver: zodResolver(decisionSchema),
    defaultValues: {
      decision: existingDecision?.decision,
      catatan: existingDecision?.catatan ?? '',
    },
  })

  const mutation = useMutation({
    mutationFn: (formData: DecisionForm) => spkApi.setDecision(evalId, formData),
    onSuccess: () => {
      toast('Keputusan HRD berhasil disimpan.', 'success')
      queryClient.invalidateQueries({ queryKey: ['transparency', evalId] })
    },
    onError: (err: AxiosError<{ message?: string }>) => {
      const msg = err.response?.data?.message ?? 'Gagal menyimpan keputusan.'
      toast(msg, 'error')
    }
  })

  return (
    <div className="bg-card border-2 border-primary/20 rounded-2xl overflow-hidden shadow-sm">
      <button
        type="button"
        onClick={() => setIsExpanded(v => !v)}
        className="w-full flex items-center justify-between p-5 bg-primary/5 hover:bg-primary/10 transition-colors"
      >
        <div className="flex items-center gap-3">
          <ShieldCheck size={20} className="text-primary" />
          <div className="text-left">
            <p className="font-bold text-foreground">Keputusan Final Kontrak</p>
            {existingDecision ? (
              <p className="text-xs text-muted-foreground mt-1">
                Saat ini: <strong className="text-foreground">{DECISION_LABELS[existingDecision.decision] ?? existingDecision.decision}</strong>
                {existingDecision.catatan && ` — "${existingDecision.catatan}"`}
              </p>
            ) : (
              <p className="text-xs text-muted-foreground mt-1">Belum ada keputusan ditetapkan</p>
            )}
          </div>
        </div>
        <ChevronDown size={18} className={cn("text-muted-foreground transition-transform", isExpanded && "rotate-180")} />
      </button>

      {isExpanded && (
        <form onSubmit={handleSubmit(d => mutation.mutate(d))} className="p-5 space-y-4 bg-white">
          <div className="bg-amber-50 border border-amber-200 rounded-xl p-3 flex gap-2">
            <Info size={16} className="text-amber-600 shrink-0 mt-0.5" />
            <p className="text-xs text-amber-800 leading-relaxed">
              <strong>SPK hanyalah rekomendasi pendukung.</strong> Keputusan akhir perpanjangan kontrak adalah wewenang penuh HRD dengan mempertimbangkan aspek kualitatif kinerja karyawan.
            </p>
          </div>

          <div className="space-y-1.5">
            <label className="text-sm font-medium">Tetapkan Keputusan</label>
            <select
              {...register('decision')}
              className={cn(
                "w-full px-3 py-2.5 rounded-xl border bg-background text-sm font-medium",
                errors.decision ? "border-destructive" : "border-border"
              )}
            >
              <option value="">-- Pilih Keputusan --</option>
              <option value="DIPERPANJANG">Diperpanjang</option>
              <option value="TIDAK_DIPERPANJANG">Tidak Diperpanjang</option>
              <option value="DITUNDA">Ditunda (Evaluasi Lanjutan)</option>
            </select>
            {errors.decision && <p className="text-destructive text-xs">{errors.decision.message}</p>}
          </div>

          <div className="space-y-1.5">
            <label className="text-sm font-medium text-muted-foreground">Catatan / Alasan (Opsional)</label>
            <textarea
              {...register('catatan')}
              rows={3}
              placeholder="Tuliskan alasan kualitatif jika ada..."
              className="w-full px-3 py-2.5 rounded-xl border border-border bg-background text-sm resize-none focus:outline-none focus:ring-2 focus:ring-primary/30"
            />
          </div>

          <div className="flex items-center justify-between pt-2">
            <p className="text-xs text-muted-foreground">
              {existingDecision ? 'Klik simpan untuk mengubah keputusan.' : ''}
            </p>
            <button
              type="submit"
              disabled={mutation.isPending}
              className="px-6 py-2.5 text-sm font-bold bg-primary text-primary-foreground rounded-xl hover:bg-primary/90 flex items-center gap-2 disabled:opacity-50 transition-transform active:scale-95"
            >
              {mutation.isPending && <Loader2 size={16} className="animate-spin" />}
              Simpan Keputusan Final
            </button>
          </div>
        </form>
      )}
    </div>
  )
}
