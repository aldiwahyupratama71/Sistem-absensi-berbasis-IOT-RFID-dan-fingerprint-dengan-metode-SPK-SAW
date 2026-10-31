import { useState, useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import { 
  PieChart, Pie, Cell, Tooltip as RechartsTooltip, ResponsiveContainer, Legend
} from 'recharts'
import { 
  FileText, Download, FileSpreadsheet, Printer, Loader2, CheckCircle2, AlertCircle, X
} from 'lucide-react'

import { spkApi } from '@/api/evaluations'
import { reportApi } from '@/api/reports'
import { cn, formatDate } from '@/lib/utils'
import type { SpkEvaluation } from '@/types/models'

// Toast type
type ToastMessage = { id: number, type: 'success' | 'error', message: string }

const CATEGORY_MAP: Record<string, { label: string, color: string, hex: string }> = {
  'positif': { label: 'Positif', color: 'bg-emerald-100 text-emerald-700 border-emerald-200', hex: '#10b981' },
  'pertimbangan': { label: 'Perlu Pertimbangan', color: 'bg-amber-100 text-amber-700 border-amber-200', hex: '#f59e0b' },
  'perhatian': { label: 'Perlu Perhatian', color: 'bg-orange-100 text-orange-700 border-orange-200', hex: '#f97316' },
  'evaluasi_khusus': { label: 'Evaluasi Khusus', color: 'bg-rose-100 text-rose-700 border-rose-200', hex: '#f43f5e' }
}

export default function LaporanPage() {
  const [selectedPeriodId, setSelectedPeriodId] = useState<number | ''>('')
  const [categoryFilter, setCategoryFilter] = useState<string>('')
  
  const [isExportingPdf, setIsExportingPdf] = useState(false)
  const [isExportingExcel, setIsExportingExcel] = useState(false)
  
  const [toasts, setToasts] = useState<ToastMessage[]>([])

  const showToast = (type: 'success' | 'error', message: string) => {
    const id = Date.now()
    setToasts(prev => [...prev, { id, type, message }])
    setTimeout(() => {
      setToasts(prev => prev.filter(t => t.id !== id))
    }, 3000)
  }

  // Data Queries
  const { data: periodsData } = useQuery({
    queryKey: ['periods', 'all'],
    queryFn: () => spkApi.getPeriods({ page: 1 })
  })
  const periods = periodsData?.data || []

  const { data: evals, isLoading } = useQuery({
    queryKey: ['evaluations', selectedPeriodId],
    queryFn: () => spkApi.getEvaluations(Number(selectedPeriodId)),
    enabled: !!selectedPeriodId
  })

  // Data Processing
  const filteredEvals = useMemo(() => {
    let result = evals?.data || []
    if (categoryFilter) {
      result = result.filter((e: SpkEvaluation) => e.kategori === categoryFilter)
    }
    return [...result].sort((a: SpkEvaluation, b: SpkEvaluation) =>
      parseFloat(String(b.nilai_akhir || 0)) - parseFloat(String(a.nilai_akhir || 0))
    )
  }, [evals, categoryFilter])

  const chartData = useMemo(() => {
    const data = evals?.data || []
    return Object.entries(CATEGORY_MAP).map(([key, val]) => ({
      name: val.label,
      value: data.filter((d: SpkEvaluation) => d.kategori === key).length,
      color: val.hex
    })).filter(item => item.value > 0)
  }, [evals])

  // Handlers
  const handleExport = async (type: 'pdf' | 'excel') => {
    if (!selectedPeriodId) return
    
    if (type === 'pdf') setIsExportingPdf(true)
    else setIsExportingExcel(true)
    
    try {
      const res = await reportApi.exportEvaluations(Number(selectedPeriodId), type) as any
      const url = window.URL.createObjectURL(new Blob([res.data]))
      const link = document.createElement('a')
      link.href = url
      const contentDisposition = res.headers['content-disposition']
      let fileName = `Laporan_SPK_${selectedPeriodId}.${type === 'pdf' ? 'pdf' : 'xlsx'}`
      if (contentDisposition) {
        const fileNameMatch = contentDisposition.match(/filename="?([^"]+)"?/)
        if (fileNameMatch && fileNameMatch.length === 2) {
          fileName = fileNameMatch[1]
        }
      }
      link.setAttribute('download', fileName)
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(url)
      
      showToast('success', `Berhasil mengunduh laporan ${type.toUpperCase()}`)
    } catch (err) {
      showToast('error', `Gagal mengunduh laporan ${type.toUpperCase()}`)
    } finally {
      if (type === 'pdf') setIsExportingPdf(false)
      else setIsExportingExcel(false)
    }
  }

  const selectedPeriod = periods.find((p: any) => p.id === Number(selectedPeriodId))

  return (
    <div className="space-y-6 pb-8 print:space-y-0 print:pb-0">
      
      {/* Toast Container */}
      <div className="fixed top-4 right-4 z-[100] space-y-2 print:hidden">
        {toasts.map(t => (
          <div key={t.id} className={cn(
            "flex items-center gap-3 px-4 py-3 rounded-lg shadow-lg border animate-sidebar-slide-in",
            t.type === 'success' ? "bg-emerald-50 border-emerald-200 text-emerald-800" : "bg-rose-50 border-rose-200 text-rose-800"
          )}>
            {t.type === 'success' ? <CheckCircle2 size={18} /> : <AlertCircle size={18} />}
            <p className="text-sm font-medium">{t.message}</p>
            <button onClick={() => setToasts(prev => prev.filter(x => x.id !== t.id))} className="ml-4 opacity-70 hover:opacity-100">
              <X size={16} />
            </button>
          </div>
        ))}
      </div>

      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 print:hidden">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Laporan & Ekspor</h1>
          <p className="text-sm text-muted-foreground">Analisis ringkasan evaluasi dan unduh dokumen siap cetak.</p>
        </div>
        
        <div className="flex items-center gap-2">
          <button 
            onClick={() => handleExport('excel')}
            disabled={!selectedPeriodId || isExportingExcel}
            className="flex items-center gap-2 px-3 py-2 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-xl text-sm font-medium hover:bg-emerald-100 transition-colors disabled:opacity-50"
          >
            {isExportingExcel ? <Loader2 size={16} className="animate-spin" /> : <FileSpreadsheet size={16} />}
            Excel
          </button>
          <button 
            onClick={() => handleExport('pdf')}
            disabled={!selectedPeriodId || isExportingPdf}
            className="flex items-center gap-2 px-3 py-2 bg-rose-50 text-rose-700 border border-rose-200 rounded-xl text-sm font-medium hover:bg-rose-100 transition-colors disabled:opacity-50"
          >
            {isExportingPdf ? <Loader2 size={16} className="animate-spin" /> : <Download size={16} />}
            PDF
          </button>
          <button 
            onClick={() => window.print()}
            disabled={!selectedPeriodId || filteredEvals.length === 0}
            className="flex items-center gap-2 px-3 py-2 bg-card border border-border rounded-xl text-sm font-medium hover:bg-accent transition-colors disabled:opacity-50"
          >
            <Printer size={16} />
            Cetak
          </button>
        </div>
      </div>

      {/* Print Header (only visible during print) */}
      <div className="hidden print:block mb-8 text-center border-b-2 border-black pb-4">
        <h1 className="text-2xl font-bold">LAPORAN HASIL EVALUASI SPK KARYAWAN</h1>
        <p className="text-sm mt-1">
          Periode: {selectedPeriod ? `Periode ${selectedPeriod.id} (Jendela: ${formatDate(selectedPeriod.tanggal_mulai)} s/d ${formatDate(selectedPeriod.tanggal_selesai)})` : '-'}
        </p>
        <p className="text-xs text-muted-foreground mt-0.5">
          Grup Kontrak: {selectedPeriod ? `${formatDate(selectedPeriod.kontrak_mulai)} - ${formatDate(selectedPeriod.kontrak_selesai)}` : '-'}
        </p>
      </div>

      {/* Toolbar */}
      <div className="bg-card border border-border rounded-2xl p-4 shadow-sm flex flex-col sm:flex-row gap-4 print:hidden">
        <div className="w-full sm:w-1/2 space-y-1.5">
          <label className="text-xs font-medium text-muted-foreground">Periode Evaluasi</label>
          <select 
            value={selectedPeriodId} 
            onChange={e => setSelectedPeriodId(Number(e.target.value) || '')}
            className="w-full px-3 py-2 bg-background border border-border rounded-lg text-sm"
          >
            <option value="">-- Pilih Periode --</option>
            {periods.map((p: any) => (
              <option key={p.id} value={p.id}>Periode {p.id} (Jendela: {formatDate(p.tanggal_mulai)} s/d {formatDate(p.tanggal_selesai)}) Grup: {formatDate(p.kontrak_mulai)} - {formatDate(p.kontrak_selesai)}</option>
            ))}
          </select>
        </div>
        <div className="w-full sm:w-1/4 space-y-1.5">
          <label className="text-xs font-medium text-muted-foreground">Filter Kategori</label>
          <select 
            value={categoryFilter} 
            onChange={e => setCategoryFilter(e.target.value)}
            disabled={!selectedPeriodId}
            className="w-full px-3 py-2 bg-background border border-border rounded-lg text-sm disabled:opacity-50"
          >
            <option value="">Semua Kategori</option>
            <option value="positif">Positif</option>
            <option value="pertimbangan">Perlu Pertimbangan</option>
            <option value="perhatian">Perlu Perhatian</option>
            <option value="evaluasi_khusus">Evaluasi Khusus</option>
          </select>
        </div>
      </div>

      {!selectedPeriodId ? (
        <div className="h-64 flex flex-col items-center justify-center text-muted-foreground border-2 border-dashed border-border rounded-2xl print:hidden">
          <FileText size={48} className="mb-4 opacity-20" />
          <p>Pilih periode untuk melihat laporan</p>
        </div>
      ) : isLoading ? (
        <div className="h-64 flex flex-col items-center justify-center text-muted-foreground print:hidden">
          <Loader2 size={32} className="animate-spin mb-4" />
          <p>Memuat laporan...</p>
        </div>
      ) : (
        <div className="space-y-6">
          
          {/* Summary Chart Area */}
          <div className="bg-card border border-border rounded-2xl p-6 shadow-sm print:hidden">
            <h2 className="font-semibold text-lg mb-6 text-center">Ringkasan Distribusi Kategori</h2>
            {chartData.length === 0 ? (
              <p className="text-center text-muted-foreground text-sm py-12">Belum ada data pada periode ini.</p>
            ) : (
              <div className="h-64 w-full flex items-center justify-center">
                <ResponsiveContainer width="100%" height="100%">
                  <PieChart>
                    <Pie
                      data={chartData}
                      innerRadius={60}
                      outerRadius={80}
                      paddingAngle={5}
                      dataKey="value"
                      label={({ name, percent }) => `${name} ${((percent || 0) * 100).toFixed(0)}%`}
                    >
                      {chartData.map((entry, index) => (
                        <Cell key={`cell-${index}`} fill={entry.color} />
                      ))}
                    </Pie>
                    <RechartsTooltip 
                      contentStyle={{ borderRadius: '12px', border: '1px solid hsl(var(--border))', fontSize: '12px' }}
                    />
                    <Legend />
                  </PieChart>
                </ResponsiveContainer>
              </div>
            )}
          </div>

          {/* Report Table */}
          <div className="bg-card border border-border rounded-2xl shadow-sm overflow-hidden print:border-none print:shadow-none print:rounded-none">
            <div className="p-4 border-b border-border print:hidden">
              <h2 className="font-semibold text-lg">Tabel Rincian Hasil</h2>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full text-sm text-left print:text-xs">
                <thead className="bg-muted/50 text-muted-foreground text-xs uppercase print:bg-transparent print:border-b-2 print:border-black">
                  <tr>
                    <th className="px-6 py-4 font-semibold text-center w-16 print:px-2 print:py-2">Rank</th>
                    <th className="px-6 py-4 font-semibold print:px-2 print:py-2">Karyawan</th>
                    <th className="px-6 py-4 font-semibold print:px-2 print:py-2">Departemen</th>
                    <th className="px-6 py-4 font-semibold text-center print:px-2 print:py-2">Nilai SPK</th>
                    <th className="px-6 py-4 font-semibold print:px-2 print:py-2">Kategori</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border print:divide-black/20">
                  {filteredEvals.length === 0 ? (
                    <tr>
                      <td colSpan={5} className="px-6 py-12 text-center text-muted-foreground print:hidden">
                        Tidak ada data yang sesuai filter.
                      </td>
                    </tr>
                  ) : (
                    filteredEvals.map((ev: SpkEvaluation, i: number) => {
                      const cat = CATEGORY_MAP[ev.kategori] || CATEGORY_MAP['evaluasi_khusus']
                      return (
                        <tr key={ev.id} className="hover:bg-muted/30 transition-colors print:hover:bg-transparent">
                          <td className="px-6 py-4 text-center font-bold print:px-2 print:py-2">{i + 1}</td>
                          <td className="px-6 py-4 font-medium print:px-2 print:py-2">{ev.nama}</td>
                          <td className="px-6 py-4 text-muted-foreground print:px-2 print:py-2">{ev.departemen}</td>
                          <td className="px-6 py-4 text-center print:px-2 print:py-2">
                            <span className="font-bold text-primary print:text-black">{ev.nilai_akhir ? parseFloat(String(ev.nilai_akhir)).toFixed(2) : '-'}%</span>
                          </td>
                          <td className="px-6 py-4 print:px-2 print:py-2">
                            {/* Colored Badge for Screen */}
                            <span className={cn("inline-flex px-2 py-0.5 text-[11px] font-bold uppercase rounded-md border print:hidden", cat.color)}>
                              {cat.label}
                            </span>
                            {/* Plain text for Print */}
                            <span className="hidden print:inline font-bold uppercase">
                              {cat.label}
                            </span>
                          </td>
                        </tr>
                      )
                    })
                  )}
                </tbody>
              </table>
            </div>
            
            {/* Print Footer */}
            <div className="hidden print:block mt-16 flex justify-end">
              <div className="w-48 text-center">
                <p className="mb-16 text-sm">Mengetahui,<br/>HR Manager</p>
                <p className="border-t border-black font-semibold text-sm pt-2">(.................)</p>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
