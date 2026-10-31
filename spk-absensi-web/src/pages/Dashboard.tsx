import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { 
  Users, UserCheck, Clock, UserMinus, Fingerprint, AlertCircle, ChevronRight, Activity, X
} from 'lucide-react'
import { 
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, PieChart, Pie, Cell, Legend
} from 'recharts'
import { cn, formatDate } from '@/lib/utils'
import { dashboardApi } from '@/api/dashboard'
import { spkApi } from '@/api/evaluations'
import type { SpkEvaluation } from '@/types/models'

// ─── Constants ─────────────────────────────────────────────────────────────
const PIE_COLORS = {
  kehadiran: ['#10B981', '#F59E0B', '#EF4444'], // Tepat Waktu, Terlambat, Tidak Hadir
  evaluasi: ['#10B981', '#EAB308', '#F97316', '#EF4444'] // Positif, Pertimbangan, Perhatian, Khusus
}

const CATEGORY_MAP: Record<string, { label: string, color: string }> = {
  'positif': { label: 'Positif', color: 'bg-emerald-100 text-emerald-700 border-emerald-200' },
  'pertimbangan': { label: 'Perlu Pertimbangan', color: 'bg-yellow-100 text-yellow-700 border-yellow-200' },
  'perhatian': { label: 'Perlu Perhatian', color: 'bg-orange-100 text-orange-700 border-orange-200' },
  'evaluasi_khusus': { label: 'Evaluasi Khusus', color: 'bg-red-100 text-red-700 border-red-200' }
}

/** Safely convert string|number|null from API to float */
const toNum = (v: unknown): number => parseFloat(String(v ?? 0)) || 0

export default function Dashboard() {
  const [selectedPeriod, setSelectedPeriod] = useState<string>('')
  const [selectedDetail, setSelectedDetail] = useState<SpkEvaluation | null>(null)
  const [detailTab, setDetailTab] = useState<'ringkasan' | 'perhitungan'>('ringkasan')

  const { data: periodsData } = useQuery({
    queryKey: ['dashboard-periods'],
    queryFn: () => spkApi.getPeriods({ page: 1 })
  })
  
  const periods = periodsData?.data || []

  // Automatically select first period if available
  if (periods.length > 0 && !selectedPeriod) {
    setSelectedPeriod(String(periods[0].id))
  }

  const { data: stats, isLoading: isStatsLoading } = useQuery({
    queryKey: ['dashboard-stats', selectedPeriod],
    queryFn: () => dashboardApi.getStats(selectedPeriod ? Number(selectedPeriod) : undefined)
  })

  const { data: evaluationData, isLoading: isEvalLoading } = useQuery({
    queryKey: ['dashboard-evaluations', selectedPeriod],
    queryFn: () => spkApi.getEvaluations(Number(selectedPeriod), { page: 1 }),
    enabled: !!selectedPeriod
  })

  // Map API response to chart format
  const attendanceDonut = stats?.rekap_kehadiran ? [
    { name: 'Tepat Waktu', value: stats.rekap_kehadiran.tepat_waktu },
    { name: 'Terlambat', value: stats.rekap_kehadiran.terlambat },
    { name: 'Tidak Hadir', value: stats.rekap_kehadiran.tidak_hadir }
  ] : []

  const evalDonut = stats?.distribusi_kategori ? [
    { name: 'Positif', value: stats.distribusi_kategori.positif.jumlah },
    { name: 'Perlu Pertimbangan', value: stats.distribusi_kategori.pertimbangan.jumlah },
    { name: 'Perlu Perhatian', value: stats.distribusi_kategori.perhatian.jumlah },
    { name: 'Evaluasi Khusus', value: stats.distribusi_kategori.evaluasi_khusus.jumlah },
  ] : []

  const avgCriteria = stats?.rata_rata_kriteria ? [
    { name: 'C1', value: stats.rata_rata_kriteria.C1 },
    { name: 'C2', value: stats.rata_rata_kriteria.C2 },
    { name: 'C3', value: stats.rata_rata_kriteria.C3 }
  ] : []

  const { data: detailData, isLoading: isDetailLoading } = useQuery({
    queryKey: ['dashboard-eval-detail', selectedDetail?.id],
    queryFn: () => spkApi.getTransparency(selectedDetail!.id),
    enabled: !!selectedDetail
  })

  return (
    <div className="space-y-6 animate-fade-in relative">
      {/* Header & Alerts */}
      <div className="flex flex-col gap-4">
        <div>
          <h1 className="text-2xl font-bold text-foreground tracking-tight">Dashboard Overview</h1>
          <p className="text-sm text-muted-foreground mt-1">Ringkasan performa absensi dan evaluasi SPK terkini.</p>
        </div>

        {/* Warning Alerts */}
        {stats?.peringatan && (
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {stats.peringatan.absen_tanpa_alasan > 0 && (
              <div className="bg-rose-50 border border-rose-200 rounded-xl p-4 flex items-start gap-3">
                <AlertCircle className="text-rose-600 shrink-0 mt-0.5" size={20} />
                <div>
                  <h4 className="text-sm font-semibold text-rose-900">Perlu Tindakan HRD</h4>
                  <p className="text-sm text-rose-700 mt-1">
                    Terdapat {stats.peringatan.absen_tanpa_alasan} data ketidakhadiran yang belum diberi alasan (Sah/Tidak Sah).
                  </p>
                  <Link to="/absensi" className="text-xs font-semibold text-rose-700 mt-2 inline-flex items-center hover:underline">
                    Verifikasi sekarang <ChevronRight size={14} />
                  </Link>
                </div>
              </div>
            )}
            {stats.peringatan.perangkat_tidak_aktif > 0 && (
              <div className="bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-start gap-3">
                <Activity className="text-amber-600 shrink-0 mt-0.5" size={20} />
                <div>
                  <h4 className="text-sm font-semibold text-amber-900">Perangkat IoT Terputus</h4>
                  <p className="text-sm text-amber-700 mt-1">
                    Ada {stats.peringatan.perangkat_tidak_aktif} perangkat absensi yang tidak aktif / kehilangan koneksi.
                  </p>
                  <Link to="/perangkat" className="text-xs font-semibold text-amber-700 mt-2 inline-flex items-center hover:underline">
                    Periksa perangkat <ChevronRight size={14} />
                  </Link>
                </div>
              </div>
            )}
          </div>
        )}
      </div>

      {/* Top 5 Stat Cards */}
      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
        <StatCard title="Total Karyawan" value={stats?.kartu.total_karyawan_aktif} icon={Users} color="text-blue-600" bg="bg-blue-100" loading={isStatsLoading} />
        <StatCard title="Tepat Waktu" value={stats?.kartu.tepat_waktu} icon={UserCheck} color="text-emerald-600" bg="bg-emerald-100" loading={isStatsLoading} />
        <StatCard title="Terlambat" value={stats?.kartu.terlambat} icon={Clock} color="text-amber-600" bg="bg-amber-100" loading={isStatsLoading} />
        <StatCard title="Tidak Hadir" value={stats?.kartu.tidak_hadir} icon={UserMinus} color="text-rose-600" bg="bg-rose-100" loading={isStatsLoading} />
        <StatCard title="Absen Fingerprint" value={stats?.kartu.absen_fingerprint} icon={Fingerprint} color="text-purple-600" bg="bg-purple-100" loading={isStatsLoading} />
      </div>

      {/* Middle Row: Charts */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Rekapitulasi Kehadiran */}
        <div className="bg-card border border-border rounded-2xl p-5 shadow-sm">
          <h3 className="font-semibold mb-4 text-sm">Rekapitulasi Kehadiran</h3>
          <div className="h-64">
            <ResponsiveContainer width="100%" height="100%">
              <PieChart>
                <Pie
                  data={attendanceDonut}
                  cx="50%" cy="50%" innerRadius={60} outerRadius={80} paddingAngle={5}
                  dataKey="value" stroke="none"
                >
                  {attendanceDonut.map((entry: any, index: number) => (
                    <Cell key={`cell-${index}`} fill={PIE_COLORS.kehadiran[index % PIE_COLORS.kehadiran.length]} />
                  ))}
                </Pie>
                <Tooltip formatter={(value: any) => [`${value} Orang`, 'Jumlah']} contentStyle={{ borderRadius: '8px' }} />
                <Legend iconType="circle" wrapperStyle={{ fontSize: '12px' }} />
              </PieChart>
            </ResponsiveContainer>
          </div>
        </div>

        {/* Distribusi Evaluasi */}
        <div className="bg-card border border-border rounded-2xl p-5 shadow-sm">
          <h3 className="font-semibold mb-4 text-sm">Distribusi Kategori SPK</h3>
          <div className="h-64">
            <ResponsiveContainer width="100%" height="100%">
              <PieChart>
                <Pie
                  data={evalDonut}
                  cx="50%" cy="50%" innerRadius={60} outerRadius={80} paddingAngle={5}
                  dataKey="value" stroke="none"
                >
                  {evalDonut.map((entry: any, index: number) => (
                    <Cell key={`cell-${index}`} fill={PIE_COLORS.evaluasi[index % PIE_COLORS.evaluasi.length]} />
                  ))}
                </Pie>
                <Tooltip formatter={(value: any) => [`${value} Karyawan`, 'Jumlah']} contentStyle={{ borderRadius: '8px' }} />
                <Legend iconType="circle" wrapperStyle={{ fontSize: '12px' }} />
              </PieChart>
            </ResponsiveContainer>
          </div>
        </div>

        {/* Rata-rata Kriteria */}
        <div className="bg-card border border-border rounded-2xl p-5 shadow-sm">
          <h3 className="font-semibold mb-4 text-sm">Rata-rata Nilai Kriteria</h3>
          <div className="h-64">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={avgCriteria} margin={{ top: 20, right: 10, left: -20, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="hsl(var(--border))" />
                <XAxis dataKey="name" tick={{ fontSize: 12 }} axisLine={false} tickLine={false} />
                <YAxis tick={{ fontSize: 12 }} axisLine={false} tickLine={false} domain={[0, 100]} />
                <Tooltip cursor={{ fill: 'hsl(var(--muted)/0.5)' }} contentStyle={{ borderRadius: '8px' }} />
                <Bar dataKey="value" radius={[6, 6, 0, 0]} maxBarSize={50}>
                  {avgCriteria.map((entry: any, index: number) => (
                    <Cell key={`cell-${index}`} fill="hsl(var(--primary))" />
                  ))}
                </Bar>
              </BarChart>
            </ResponsiveContainer>
          </div>
        </div>
      </div>

      {/* Bottom Table: Daftar Evaluasi */}
      <div className="bg-card border border-border rounded-2xl shadow-sm overflow-hidden flex flex-col">
        <div className="p-5 border-b border-border flex flex-col sm:flex-row justify-between items-center gap-4">
          <h3 className="font-semibold text-foreground">Daftar Evaluasi SPK</h3>
          <div className="flex items-center gap-3 w-full sm:w-auto">
            <select 
              value={selectedPeriod} 
              onChange={(e) => setSelectedPeriod(e.target.value)}
              className="px-3 py-2 text-sm rounded-lg border border-border bg-background flex-1 sm:w-64"
            >
              {periods.map((p: any) => (
                <option key={p.id} value={p.id}>Periode {p.id} ({formatDate(p.tanggal_mulai)} s.d. {formatDate(p.tanggal_selesai)}) Grup: {formatDate(p.kontrak_mulai)} - {formatDate(p.kontrak_selesai)}</option>
              ))}
              {periods.length === 0 && <option value="">Tidak ada periode aktif</option>}
            </select>
            <Link to="/laporan" className="px-4 py-2 text-sm font-medium bg-primary text-primary-foreground rounded-lg hover:bg-primary/90 whitespace-nowrap">
              Export Laporan
            </Link>
          </div>
        </div>
        
        <div className="overflow-x-auto">
          <table className="w-full text-sm text-left">
            <thead className="bg-muted/50 text-muted-foreground uppercase text-xs">
              <tr>
                <th className="px-6 py-4 font-semibold">No</th>
                <th className="px-6 py-4 font-semibold">Karyawan</th>
                <th className="px-6 py-4 font-semibold text-center">C1</th>
                <th className="px-6 py-4 font-semibold text-center">C2</th>
                <th className="px-6 py-4 font-semibold text-center">C3</th>
                <th className="px-6 py-4 font-semibold text-center">Nilai Akhir</th>
                <th className="px-6 py-4 font-semibold">Kategori</th>
                <th className="px-6 py-4 font-semibold text-right">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {isEvalLoading ? (
                <tr>
                  <td colSpan={8} className="px-6 py-12 text-center text-muted-foreground">Memuat data evaluasi...</td>
                </tr>
              ) : evaluationData?.data?.length === 0 ? (
                <tr>
                  <td colSpan={8} className="px-6 py-12 text-center text-muted-foreground">Belum ada evaluasi untuk periode ini.</td>
                </tr>
              ) : (
                evaluationData?.data?.map((item: any, idx: number) => {
                  const cat = CATEGORY_MAP[item.kategori] || CATEGORY_MAP['perhatian']
                  return (
                    <tr key={item.id} className="hover:bg-muted/30 transition-colors">
                      <td className="px-6 py-4 text-muted-foreground">{idx + 1}</td>
                      <td className="px-6 py-4">
                        <p className="font-semibold text-foreground">{item.nama || '-'}</p>
                        <p className="text-xs text-muted-foreground">{item.departemen || '-'}</p>
                      </td>
                      <td className="px-6 py-4 text-center font-mono">{item.C1 ?? '-'}</td>
                      <td className="px-6 py-4 text-center font-mono">{item.C2 ?? '-'}</td>
                      <td className="px-6 py-4 text-center font-mono">{item.C3 ?? '-'}</td>
                      <td className="px-6 py-4 text-center font-bold text-primary">{item.nilai_akhir != null ? toNum(item.nilai_akhir).toFixed(2) : '-'}%</td>
                      <td className="px-6 py-4">
                        <span className={cn("px-2.5 py-1 text-xs font-semibold rounded-full border", cat.color)}>
                          {cat.label}
                        </span>
                      </td>
                      <td className="px-6 py-4 text-right">
                        <button 
                          onClick={() => setSelectedDetail(item)}
                          className="px-3 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors"
                        >
                          Detail
                        </button>
                      </td>
                    </tr>
                  )
                })
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Sliding Detail Panel */}
      {selectedDetail && (
        <div className="fixed inset-0 z-50 flex justify-end">
          <div className="absolute inset-0 bg-black/50 animate-fade-in" onClick={() => setSelectedDetail(null)} />
          <div className="relative w-full max-w-xl bg-background h-full shadow-2xl flex flex-col animate-sidebar-slide-in" style={{ transformOrigin: 'right' }}>
            <div className="p-6 border-b border-border bg-card flex justify-between items-start">
              <div>
                <h2 className="text-xl font-bold">{selectedDetail.nama}</h2>
                <p className="text-sm text-muted-foreground">{selectedDetail.departemen}</p>
              </div>
              <button onClick={() => setSelectedDetail(null)} className="p-2 text-muted-foreground hover:bg-muted rounded-full transition-colors"><X size={20} /></button>
            </div>

            <div className="flex border-b border-border bg-muted/20">
              <button 
                onClick={() => setDetailTab('ringkasan')}
                className={cn("flex-1 px-4 py-3 text-sm font-medium border-b-2 transition-colors", detailTab === 'ringkasan' ? "border-primary text-primary bg-background" : "border-transparent text-muted-foreground hover:bg-muted/50")}
              >
                Ringkasan Hasil
              </button>
              <button 
                onClick={() => setDetailTab('perhitungan')}
                className={cn("flex-1 px-4 py-3 text-sm font-medium border-b-2 transition-colors", detailTab === 'perhitungan' ? "border-primary text-primary bg-background" : "border-transparent text-muted-foreground hover:bg-muted/50")}
              >
                Detail Perhitungan SAW
              </button>
            </div>

            <div className="p-6 flex-1 overflow-y-auto">
              {isDetailLoading ? (
                <div className="text-center py-10 text-muted-foreground">Memuat detail evaluasi...</div>
              ) : !detailData ? (
                <div className="text-center py-10 text-muted-foreground">Detail tidak ditemukan.</div>
              ) : (
                <>
                  {detailTab === 'ringkasan' ? (
                    <div className="space-y-6">
                      <div className="p-4 bg-muted/50 rounded-xl border border-border text-center">
                        <p className="text-sm text-muted-foreground mb-1">Nilai Akhir</p>
                        <p className="text-4xl font-black text-primary">{toNum(detailData.nilai_akhir).toFixed(2)}<span className="text-2xl text-muted-foreground">%</span></p>
                        <div className="mt-3 inline-block">
                          <span className={cn("px-3 py-1 text-sm font-semibold rounded-full border", CATEGORY_MAP[detailData.kategori]?.color)}>
                            {CATEGORY_MAP[detailData.kategori]?.label}
                          </span>
                        </div>
                      </div>
                      <div className="bg-amber-50 border border-amber-200 p-3 rounded-lg text-amber-800 text-xs italic text-center">
                        * Rekomendasi ini bersifat pendukung, keputusan akhir sepenuhnya ada pada HRD.
                      </div>
                    </div>
                  ) : (
                    <div className="space-y-4">
                      <table className="w-full text-sm border border-border rounded-lg overflow-hidden">
                        <thead className="bg-muted">
                          <tr>
                            <th className="px-3 py-2 text-left font-medium">Kriteria</th>
                            <th className="px-3 py-2 text-right font-medium">Mentah</th>
                            <th className="px-3 py-2 text-right font-medium">Normalisasi</th>
                            <th className="px-3 py-2 text-right font-medium">Bobot</th>
                            <th className="px-3 py-2 text-right font-medium">Kontribusi</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                          {detailData.details?.map((c: any, i: number) => (
                            <tr key={i}>
                              <td className="px-3 py-2 font-medium">{c.kode}</td>
                              <td className="px-3 py-2 text-right">{toNum(c.nilai_mentah).toFixed(2)}</td>
                              <td className="px-3 py-2 text-right">{toNum(c.nilai_normalisasi).toFixed(4)}</td>
                              <td className="px-3 py-2 text-right text-muted-foreground">{c.bobot}%</td>
                              <td className="px-3 py-2 text-right font-semibold text-primary">{toNum(c.kontribusi).toFixed(2)}%</td>
                            </tr>
                          ))}
                          <tr className="bg-muted/30">
                            <td colSpan={4} className="px-3 py-3 text-right font-bold">Total Nilai Akhir</td>
                            <td className="px-3 py-3 text-right font-bold text-primary">{toNum(detailData.nilai_akhir).toFixed(2)}%</td>
                          </tr>
                        </tbody>
                      </table>
                      <p className="text-xs text-muted-foreground text-center">Angka normalisasi dihitung menggunakan metode Simple Additive Weighting secara proporsional dari batas minimum dan maksimum dataset.</p>
                    </div>
                  )}
                </>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

function StatCard({ title, value, icon: Icon, color, bg, loading }: { title: string, value?: number, icon: React.ElementType, color: string, bg: string, loading: boolean }) {
  return (
    <div className="bg-card border border-border rounded-2xl p-4 shadow-sm flex flex-col justify-between h-full relative overflow-hidden group">
      <div className="flex justify-between items-start mb-4">
        <p className="text-sm font-medium text-muted-foreground">{title}</p>
        <div className={cn("w-10 h-10 rounded-xl flex items-center justify-center shrink-0 transition-transform group-hover:scale-110", bg, color)}>
          <Icon size={20} />
        </div>
      </div>
      <div>
        {loading ? (
          <div className="h-8 w-16 bg-muted animate-pulse rounded-md"></div>
        ) : (
          <h3 className="text-3xl font-black text-foreground">{value || 0}</h3>
        )}
      </div>
    </div>
  )
}
