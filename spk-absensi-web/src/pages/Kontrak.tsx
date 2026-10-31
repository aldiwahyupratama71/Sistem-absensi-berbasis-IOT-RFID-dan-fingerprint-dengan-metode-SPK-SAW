import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { 
  FileText, Plus, Edit2, Trash2, Users, X, CheckCircle2, Clock, AlertTriangle, Loader2
} from 'lucide-react'
import { Pagination } from '@/components/shared/Pagination'
import { AxiosError } from 'axios'

import { contractApi } from '@/api/contracts'
import { employeeApi } from '@/api/employees'
import { spkApi } from '@/api/evaluations'
import { cn, formatDate } from '@/lib/utils'
import type { Contract, ContractGroup, EvaluationPeriod } from '@/types/models'

// ─── Zod Schemas ───────────────────────────────────────────────────────────
const contractSchema = z.object({
  employee_id: z.string().min(1, 'Karyawan wajib dipilih'),
  tanggal_mulai: z.string().min(1, 'Tanggal mulai wajib diisi'),
  tanggal_selesai: z.string().min(1, 'Tanggal berakhir wajib diisi'),
  status: z.enum(['aktif', 'selesai'] as const)
}).refine(data => data.tanggal_mulai <= data.tanggal_selesai, {
  message: "Tanggal akhir harus lebih besar atau sama dengan tanggal mulai",
  path: ['tanggal_selesai']
})
type ContractForm = z.infer<typeof contractSchema>

const periodSchema = z.object({
  kontrak_mulai: z.string(),
  kontrak_selesai: z.string(),
  tanggal_mulai: z.string().min(1, 'Tanggal mulai wajib diisi'),
  tanggal_selesai: z.string().min(1, 'Tanggal berakhir wajib diisi'),
}).superRefine((val, ctx) => {
  if (val.tanggal_mulai > val.tanggal_selesai) {
    ctx.addIssue({ code: z.ZodIssueCode.custom, message: "Tanggal akhir tidak boleh kurang dari tanggal mulai", path: ['tanggal_selesai'] })
  }
  if (val.tanggal_mulai < val.kontrak_mulai || val.tanggal_selesai > val.kontrak_selesai) {
    ctx.addIssue({ code: z.ZodIssueCode.custom, message: "Periode harus berada dalam rentang kontrak grup", path: ['tanggal_selesai'] })
    ctx.addIssue({ code: z.ZodIssueCode.custom, message: "Berada di luar kontrak", path: ['tanggal_mulai'] })
  }
})
type PeriodForm = z.infer<typeof periodSchema>


// ─── Main Component ────────────────────────────────────────────────────────
export default function KontrakPage() {
  const [activeTab, setActiveTab] = useState<'kontrak' | 'periode'>('kontrak')

  return (
    <div className="space-y-6 pb-8">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Kontrak & Periode</h1>
          <p className="text-sm text-muted-foreground mt-1">Kelola kontrak individual dan atur periode evaluasi berbasis grup.</p>
        </div>
      </div>

      <div className="flex gap-2 p-1 bg-muted rounded-xl w-fit">
        <button 
          onClick={() => setActiveTab('kontrak')}
          className={cn("px-4 py-2 rounded-lg text-sm font-medium transition-all", activeTab === 'kontrak' ? "bg-background shadow text-foreground" : "text-muted-foreground hover:text-foreground")}
        >
          Daftar Kontrak
        </button>
        <button 
          onClick={() => setActiveTab('periode')}
          className={cn("px-4 py-2 rounded-lg text-sm font-medium transition-all", activeTab === 'periode' ? "bg-background shadow text-foreground" : "text-muted-foreground hover:text-foreground")}
        >
          Grup & Periode
        </button>
      </div>

      {activeTab === 'kontrak' ? <KontrakListTab /> : <GroupPeriodeTab />}
    </div>
  )
}

function KontrakListTab() {
  const queryClient = useQueryClient()
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(10)
  
  const [isContractFormOpen, setIsContractFormOpen] = useState(false)
  const [selectedContract, setSelectedContract] = useState<Contract | null>(null)
  
  const [isDeleteOpen, setIsDeleteOpen] = useState(false)
  const [contractToDelete, setContractToDelete] = useState<Contract | null>(null)

  const { data, isLoading } = useQuery({
    queryKey: ['contracts', page, perPage],
    queryFn: () => contractApi.getAll({ page, per_page: perPage })
  })

  return (
    <div className="space-y-4 animate-fade-in">
      <div className="flex justify-end">
        <button 
          onClick={() => { setSelectedContract(null); setIsContractFormOpen(true) }}
          className="flex items-center gap-2 px-4 py-2 bg-primary text-primary-foreground rounded-xl text-sm font-medium hover:bg-primary/90 transition-colors"
        >
          <Plus size={16} />
          Tambah Kontrak
        </button>
      </div>

      {/* Table */}
      <div className="bg-card border border-border rounded-2xl shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm text-left">
            <thead className="bg-muted/50 text-muted-foreground uppercase text-xs">
              <tr>
                <th className="px-6 py-4 font-semibold">Karyawan</th>
                <th className="px-6 py-4 font-semibold">Grup / Durasi</th>
                <th className="px-6 py-4 font-semibold">Status</th>
                <th className="px-6 py-4 font-semibold text-right">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {isLoading ? (
                <tr><td colSpan={4} className="px-6 py-8 text-center text-muted-foreground flex items-center justify-center gap-2"><Loader2 size={16} className="animate-spin" /> Memuat data...</td></tr>
              ) : data?.data.length === 0 ? (
                <tr><td colSpan={4} className="px-6 py-8 text-center text-muted-foreground">Tidak ada kontrak.</td></tr>
              ) : (
                data?.data.map((contract) => (
                  <tr key={contract.id} className="hover:bg-muted/30 transition-colors">
                    <td className="px-6 py-4">
                      <p className="font-semibold text-foreground">{contract.employee?.nama}</p>
                      <p className="text-xs text-muted-foreground mt-0.5">{contract.employee?.nip} • {contract.employee?.departemen}</p>
                    </td>
                    <td className="px-6 py-4">
                      <div className="inline-flex items-center gap-1.5 bg-blue-50 text-blue-700 px-2.5 py-1 rounded-md text-xs font-semibold mb-1 border border-blue-100">
                        {formatDate(contract.tanggal_mulai)} - {formatDate(contract.tanggal_selesai)}
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <ContractStatusBadge status={contract.status} />
                    </td>
                    <td className="px-6 py-4 text-right">
                      <div className="flex items-center justify-end gap-2">
                        <button onClick={() => { setSelectedContract(contract); setIsContractFormOpen(true) }} className="p-1.5 text-amber-600 hover:bg-amber-50 rounded-lg transition-colors" title="Edit">
                          <Edit2 size={16} />
                        </button>
                        <button onClick={() => { setContractToDelete(contract); setIsDeleteOpen(true) }} className="p-1.5 text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" title="Hapus">
                          <Trash2 size={16} />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
        
        {data?.meta && (
          <Pagination
            meta={{
              current_page: data.meta.current_page,
              last_page: data.meta.last_page,
              from: data.meta.from,
              to: data.meta.to,
              total: data.meta.total,
            }}
            onPageChange={(p) => { setPage(p) }}
            perPage={perPage}
            onPerPageChange={(pp) => { setPerPage(pp); setPage(1) }}
          />
        )}
      </div>

      {isContractFormOpen && (
        <ContractFormModal 
          contract={selectedContract} 
          onClose={() => setIsContractFormOpen(false)} 
          onSuccess={() => { setIsContractFormOpen(false); queryClient.invalidateQueries({ queryKey: ['contracts'] }) }}
        />
      )}

      {isDeleteOpen && contractToDelete && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="absolute inset-0 bg-black/50" onClick={() => setIsDeleteOpen(false)} />
          <div className="relative bg-card border border-border rounded-2xl w-full max-w-sm shadow-xl p-6">
            <h3 className="font-semibold text-lg mb-2">Hapus Kontrak</h3>
            <p className="text-sm text-muted-foreground mb-6">
              Yakin ingin menghapus kontrak {contractToDelete.employee?.nama}?
            </p>
            <div className="flex justify-end gap-3">
              <button onClick={() => setIsDeleteOpen(false)} className="px-4 py-2 text-sm font-medium hover:bg-muted rounded-lg">Batal</button>
              <button 
                onClick={async () => {
                  await contractApi.delete(contractToDelete.id)
                  queryClient.invalidateQueries({ queryKey: ['contracts'] })
                  setIsDeleteOpen(false)
                }} 
                className="px-4 py-2 text-sm font-medium bg-destructive text-destructive-foreground rounded-lg hover:bg-destructive/90"
              >
                Hapus
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

function GroupPeriodeTab() {
  const queryClient = useQueryClient()
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(10)

  const [isFormOpen, setIsFormOpen] = useState(false)
  const [selectedGroup, setSelectedGroup] = useState<ContractGroup | null>(null)
  
  const [isDeleteOpen, setIsDeleteOpen] = useState(false)
  const [periodToDelete, setPeriodToDelete] = useState<EvaluationPeriod | null>(null)

  const { data: groups } = useQuery({
    queryKey: ['contract-groups'],
    queryFn: () => contractApi.getGroups()
  })

  const { data: periods, isLoading } = useQuery({
    queryKey: ['evaluation-periods', page, perPage],
    queryFn: () => spkApi.getPeriods({ page, per_page: perPage })
  })

  const delMutation = useMutation({
    mutationFn: (params: { id: number, force?: boolean }) => spkApi.deletePeriod(params.id, { force: params.force }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['evaluation-periods'] })
      queryClient.invalidateQueries({ queryKey: ['contract-groups'] })
      setIsDeleteOpen(false)
    }
  })

  // Combine groups with periods or list groups without periods first
  const groupsWithoutPeriods = groups?.filter((g: any) => !g.sudah_punya_periode) || []

  return (
    <div className="space-y-6 animate-fade-in">
      
      {/* Groups missing periods */}
      {groupsWithoutPeriods.length > 0 && (
        <div className="bg-amber-50 border border-amber-200 rounded-2xl p-5">
          <div className="flex items-start gap-3">
            <AlertTriangle className="text-amber-600 mt-0.5 shrink-0" size={20} />
            <div className="flex-1">
              <h3 className="font-semibold text-amber-900">Grup Kontrak Belum Punya Periode Evaluasi</h3>
              <p className="text-sm text-amber-700 mt-1 mb-4">Grup kontrak ini memiliki karyawan namun belum ada jadwal evaluasi yang dibuat.</p>
              
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                {groupsWithoutPeriods.map((group: any) => (
                  <div key={group.nama_grup} className="bg-white rounded-xl p-4 border border-amber-200 shadow-sm flex items-center justify-between">
                    <div>
                      <p className="font-semibold text-sm">{formatDate(group.kontrak_mulai)} - {formatDate(group.kontrak_selesai)}</p>
                      <p className="text-xs text-muted-foreground mt-1 flex items-center gap-1"><Users size={12}/> {group.jumlah_karyawan} Karyawan</p>
                    </div>
                    <button 
                      onClick={() => { setSelectedGroup(group); setIsFormOpen(true) }}
                      className="px-3 py-1.5 bg-amber-100 text-amber-800 hover:bg-amber-200 font-medium text-xs rounded-lg transition-colors"
                    >
                      Buat Periode
                    </button>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Periods Table */}
      <div className="bg-card border border-border rounded-2xl shadow-sm overflow-hidden">
        <div className="p-5 border-b border-border flex items-center justify-between">
          <h3 className="font-semibold">Daftar Periode Evaluasi</h3>
          {groupsWithoutPeriods.length === 0 && (
            <button 
              onClick={() => { setSelectedGroup(null); setIsFormOpen(true) }}
              className="flex items-center gap-2 px-3 py-1.5 bg-primary text-primary-foreground rounded-lg text-sm font-medium hover:bg-primary/90 transition-colors"
            >
              <Plus size={16} /> Buat
            </button>
          )}
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-sm text-left">
            <thead className="bg-muted/50 text-muted-foreground uppercase text-xs">
              <tr>
                <th className="px-6 py-4 font-semibold">Grup Kontrak</th>
                <th className="px-6 py-4 font-semibold">Jendela Evaluasi</th>
                <th className="px-6 py-4 font-semibold">Progres</th>
                <th className="px-6 py-4 font-semibold">Status</th>
                <th className="px-6 py-4 font-semibold text-right">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {isLoading ? (
                <tr><td colSpan={5} className="px-6 py-8 text-center text-muted-foreground flex items-center justify-center gap-2"><Loader2 size={16} className="animate-spin" /> Memuat data...</td></tr>
              ) : periods?.data.length === 0 ? (
                <tr><td colSpan={5} className="px-6 py-8 text-center text-muted-foreground">Belum ada periode evaluasi.</td></tr>
              ) : (
                periods?.data.map((p) => {
                  const grupLabel = `${formatDate(p.kontrak_mulai)} - ${formatDate(p.kontrak_selesai)}`
                  return (
                    <tr key={p.id} className="hover:bg-muted/30 transition-colors">
                      <td className="px-6 py-4">
                        <p className="font-semibold text-foreground">{grupLabel}</p>
                        <p className="text-xs text-muted-foreground mt-0.5 flex items-center gap-1">
                          <Users size={12}/> {p.jumlah_anggota} Anggota
                        </p>
                      </td>
                      <td className="px-6 py-4">
                        <p className="font-medium text-indigo-700 bg-indigo-50 px-2 py-1 inline-flex rounded border border-indigo-100 text-xs">
                          {formatDate(p.tanggal_mulai)} s/d {formatDate(p.tanggal_selesai)}
                        </p>
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex items-center gap-2">
                          <div className="h-2 w-24 bg-muted rounded-full overflow-hidden">
                            <div 
                              className="h-full bg-primary" 
                              style={{ width: p.jumlah_anggota > 0 ? `${(p.jumlah_terhitung / p.jumlah_anggota) * 100}%` : '0%' }}
                            />
                          </div>
                          <span className="text-xs font-medium">{p.jumlah_terhitung}/{p.jumlah_anggota}</span>
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        {p.status_evaluasi === 'sudah_dihitung' ? (
                          <span className="flex w-fit items-center gap-1 text-[10px] font-bold uppercase tracking-wide text-emerald-700 bg-emerald-100 px-2 py-1 rounded border border-emerald-200">
                            <CheckCircle2 size={12} /> Selesai
                          </span>
                        ) : p.status_evaluasi === 'sebagian_dihitung' ? (
                          <span className="flex w-fit items-center gap-1 text-[10px] font-bold uppercase tracking-wide text-indigo-700 bg-indigo-100 px-2 py-1 rounded border border-indigo-200">
                            <Clock size={12} /> Sebagian
                          </span>
                        ) : (
                          <span className="flex w-fit items-center gap-1 text-[10px] font-bold uppercase tracking-wide text-amber-700 bg-amber-100 px-2 py-1 rounded border border-amber-200">
                            <Clock size={12} /> Belum
                          </span>
                        )}
                      </td>
                      <td className="px-6 py-4 text-right">
                        <div className="flex items-center justify-end gap-2">
                          <a 
                            href={`/evaluasi?period_id=${p.id}`}
                            className="px-2.5 py-1.5 text-xs font-medium text-primary bg-primary/10 hover:bg-primary/20 rounded-lg transition-colors flex items-center gap-1.5"
                          >
                            Ke Evaluasi SPK
                          </a>
                          <button onClick={() => { setPeriodToDelete(p); setIsDeleteOpen(true) }} className="p-1.5 text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" title="Hapus">
                            <Trash2 size={16} />
                          </button>
                        </div>
                      </td>
                    </tr>
                  )
                })
              )}
            </tbody>
          </table>
        </div>
        
        {periods?.meta && (
          <Pagination
            meta={{
              current_page: periods.meta.current_page,
              last_page: periods.meta.last_page,
              from: periods.meta.from,
              to: periods.meta.to,
              total: periods.meta.total,
            }}
            onPageChange={(p) => { setPage(p) }}
            perPage={perPage}
            onPerPageChange={(pp) => { setPerPage(pp); setPage(1) }}
          />
        )}
      </div>

      {isFormOpen && (
        <PeriodFormModal 
          preselectedGroup={selectedGroup}
          groups={groupsWithoutPeriods}
          onClose={() => setIsFormOpen(false)} 
          onSuccess={() => { setIsFormOpen(false); queryClient.invalidateQueries({ queryKey: ['evaluation-periods'] }); queryClient.invalidateQueries({ queryKey: ['contract-groups'] }) }}
        />
      )}

      {isDeleteOpen && periodToDelete && (
        <div className="fixed inset-0 z-[70] flex items-center justify-center p-4">
          <div className="absolute inset-0 bg-black/60" onClick={() => setIsDeleteOpen(false)} />
          <div className="relative bg-card border border-border rounded-2xl w-full max-w-sm shadow-2xl p-6 animate-fade-in">
            <div className="flex items-center gap-3 mb-3">
              <div className="w-10 h-10 rounded-full bg-rose-100 flex items-center justify-center shrink-0">
                <AlertTriangle size={20} className="text-rose-600" />
              </div>
              <h3 className="font-semibold text-lg">Hapus Periode</h3>
            </div>
            <p className="text-sm text-muted-foreground mb-1">
              Yakin ingin menghapus periode untuk grup:
            </p>
            <p className="text-sm font-semibold mb-4">
              {formatDate(periodToDelete.kontrak_mulai)} — {formatDate(periodToDelete.kontrak_selesai)}
            </p>
            {periodToDelete.status_evaluasi !== 'belum_dihitung' && (
              <div className="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4 text-xs text-amber-800">
                <p className="font-semibold mb-1">⚠️ Peringatan Dampak</p>
                Terdapat <strong>{periodToDelete.jumlah_terhitung} hasil evaluasi</strong> yang sudah dihitung. Menghapus periode ini akan ikut menghapus semua hasil tersebut.
              </div>
            )}
            <div className="flex justify-end gap-3">
              <button 
                onClick={() => setIsDeleteOpen(false)} 
                className="px-4 py-2 text-sm font-medium hover:bg-muted rounded-lg transition-colors"
              >
                Batal
              </button>
              <button 
                onClick={() => delMutation.mutate({ id: periodToDelete.id, force: true })}
                disabled={delMutation.isPending}
                className="px-4 py-2 text-sm font-medium bg-destructive text-destructive-foreground rounded-lg hover:bg-destructive/90 disabled:opacity-50 flex items-center gap-2"
              >
                {delMutation.isPending && <Loader2 size={14} className="animate-spin" />}
                Hapus Paksa
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

function PeriodFormModal({ preselectedGroup, groups, onClose, onSuccess }: { preselectedGroup: ContractGroup | null, groups: ContractGroup[], onClose: () => void, onSuccess: () => void }) {
  const { register, handleSubmit, formState: { errors }, watch, setValue } = useForm<PeriodForm>({
    resolver: zodResolver(periodSchema),
    defaultValues: preselectedGroup ? {
      kontrak_mulai: preselectedGroup.kontrak_mulai,
      kontrak_selesai: preselectedGroup.kontrak_selesai,
    } : {}
  })

  const mutation = useMutation({
    mutationFn: (data: PeriodForm) => spkApi.createPeriod({ ...data, status: 'berjalan' }),
    onSuccess
  })

  const selectedGroupId = watch('kontrak_mulai') + '|' + watch('kontrak_selesai')
  const handleGroupChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const val = e.target.value
    if (val) {
      const [m, s] = val.split('|')
      setValue('kontrak_mulai', m)
      setValue('kontrak_selesai', s)
    } else {
      setValue('kontrak_mulai', '')
      setValue('kontrak_selesai', '')
    }
  }

  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/50" onClick={onClose} />
      <div className="relative bg-card border border-border rounded-2xl w-full max-w-md shadow-xl animate-fade-in">
        <div className="flex items-center justify-between p-4 border-b border-border">
          <h3 className="font-semibold">Buat Periode Evaluasi</h3>
          <button onClick={onClose} className="p-1 text-muted-foreground hover:bg-muted rounded-full"><X size={18} /></button>
        </div>
        <form onSubmit={handleSubmit((d) => mutation.mutate(d))} className="p-4 space-y-4">
          <div className="space-y-1">
            <label className="text-sm font-medium">Pilih Grup Kontrak</label>
            <select 
              value={selectedGroupId} 
              onChange={handleGroupChange}
              className={cn("w-full px-3 py-2 rounded-lg border bg-background text-sm", (errors.kontrak_mulai || errors.kontrak_selesai) && "border-destructive")}
            >
              <option value="|">-- Pilih Grup --</option>
              {groups.map(g => {
                const val = `${g.kontrak_mulai}|${g.kontrak_selesai}`
                const label = `${formatDate(g.kontrak_mulai)} - ${formatDate(g.kontrak_selesai)} (${g.jumlah_karyawan} Karyawan)`
                return <option key={val} value={val}>{label}</option>
              })}
            </select>
            {(errors.kontrak_mulai || errors.kontrak_selesai) && <p className="text-destructive text-xs">Pilih grup yang valid</p>}
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1">
              <label className="text-sm font-medium">Tgl Mulai Evaluasi</label>
              <input type="date" {...register('tanggal_mulai')} className={cn("w-full px-3 py-2 rounded-lg border bg-background text-sm", errors.tanggal_mulai && "border-destructive")} />
              {errors.tanggal_mulai && <p className="text-destructive text-xs">{errors.tanggal_mulai.message}</p>}
            </div>
            <div className="space-y-1">
              <label className="text-sm font-medium">Tgl Akhir Evaluasi</label>
              <input type="date" {...register('tanggal_selesai')} className={cn("w-full px-3 py-2 rounded-lg border bg-background text-sm", errors.tanggal_selesai && "border-destructive")} />
              {errors.tanggal_selesai && <p className="text-destructive text-xs">{errors.tanggal_selesai.message}</p>}
            </div>
          </div>
          
          <div className="bg-blue-50 text-blue-800 p-3 rounded-lg text-xs border border-blue-100 flex items-start gap-2">
            <FileText size={16} className="shrink-0 mt-0.5 text-blue-600" />
            <p>Contoh: Kontrak 3 bulan biasanya dievaluasi menggunakan data kehadiran pada 2 bulan pertama.</p>
          </div>

          <div className="flex justify-end gap-3 pt-4 border-t border-border mt-2">
            <button type="button" onClick={onClose} className="px-4 py-2 text-sm font-medium hover:bg-muted rounded-lg">Batal</button>
            <button type="submit" disabled={mutation.isPending} className="px-4 py-2 text-sm font-medium bg-primary text-primary-foreground rounded-lg hover:bg-primary/90">
              Buat Periode
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

function ContractStatusBadge({ status }: { status: Contract['status'] }) {
  const styles: Record<string, string> = {
    aktif: "bg-emerald-100 text-emerald-700 border-emerald-200",
    selesai: "bg-amber-100 text-amber-700 border-amber-200",
  }
  const labels: Record<string, string> = {
    aktif: 'Aktif',
    selesai: 'Selesai',
  }
  return (
    <span className={cn("inline-flex px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider rounded-md border", styles[status] || "bg-muted")}>
      {labels[status] || status}
    </span>
  )
}

function ContractFormModal({ contract, onClose, onSuccess }: { contract: Contract | null, onClose: () => void, onSuccess: () => void }) {
  const isEdit = !!contract
  const { data: empRes } = useQuery({
    queryKey: ['employees', 'all'],
    queryFn: () => employeeApi.getAll({ page: 1, per_page: 100 }) // per_page besar agar semua karyawan muncul di dropdown
  })

  const { register, handleSubmit, formState: { errors }, setError } = useForm<ContractForm>({
    resolver: zodResolver(contractSchema),
    defaultValues: contract ? {
      employee_id: String(contract.employee_id),
      tanggal_mulai: contract.tanggal_mulai,
      tanggal_selesai: contract.tanggal_selesai,
      status: contract.status
    } : { status: 'aktif', employee_id: '' }
  })

  const mutation = useMutation({
    mutationFn: (data: ContractForm) => {
      const payload = { ...data, employee_id: Number(data.employee_id) }
      return isEdit ? contractApi.update(contract.id, payload) : contractApi.create(payload)
    },
    onSuccess,
    onError: (err: AxiosError<any>) => {
      if (err.response?.status === 422 && err.response.data.errors) {
        Object.entries(err.response.data.errors).forEach(([field, messages]: [string, unknown]) => {
          setError(field as keyof ContractForm, { type: 'server', message: (messages as string[])[0] })
        })
      }
    }
  })

  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/50" onClick={onClose} />
      <div className="relative bg-card border border-border rounded-2xl w-full max-w-md shadow-xl animate-fade-in">
        <div className="flex items-center justify-between p-4 border-b border-border">
          <h3 className="font-semibold">{isEdit ? 'Edit Kontrak' : 'Tambah Kontrak'}</h3>
          <button onClick={onClose} className="p-1 text-muted-foreground hover:bg-muted rounded-full"><X size={18} /></button>
        </div>
        <form onSubmit={handleSubmit((d) => mutation.mutate(d))} className="p-4 space-y-4">
          <div className="space-y-1">
            <label className="text-sm font-medium">Karyawan</label>
            <select {...register('employee_id')} className={cn("w-full px-3 py-2 rounded-lg border bg-background text-sm", errors.employee_id && "border-destructive")}>
              <option value="">-- Pilih Karyawan --</option>
              {empRes?.data.map(e => <option key={e.id} value={e.id}>{e.nama} ({e.nip})</option>)}
            </select>
            {errors.employee_id && <p className="text-destructive text-xs">{errors.employee_id.message}</p>}
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1">
              <label className="text-sm font-medium">Tgl Mulai</label>
              <input type="date" {...register('tanggal_mulai')} className={cn("w-full px-3 py-2 rounded-lg border bg-background text-sm", errors.tanggal_mulai && "border-destructive")} />
              {errors.tanggal_mulai && <p className="text-destructive text-xs">{errors.tanggal_mulai.message}</p>}
            </div>
            <div className="space-y-1">
              <label className="text-sm font-medium">Tgl Berakhir</label>
              <input type="date" {...register('tanggal_selesai')} className={cn("w-full px-3 py-2 rounded-lg border bg-background text-sm", errors.tanggal_selesai && "border-destructive")} />
              {errors.tanggal_selesai && <p className="text-destructive text-xs">{errors.tanggal_selesai.message}</p>}
            </div>
          </div>
          <div className="space-y-1">
            <label className="text-sm font-medium">Status</label>
            <select {...register('status')} className="w-full px-3 py-2 rounded-lg border border-border bg-background text-sm">
              <option value="aktif">Aktif</option>
              <option value="selesai">Selesai</option>
            </select>
          </div>
          <div className="flex justify-end gap-3 pt-4 border-t border-border mt-2">
            <button type="button" onClick={onClose} className="px-4 py-2 text-sm font-medium hover:bg-muted rounded-lg">Batal</button>
            <button type="submit" disabled={mutation.isPending} className="px-4 py-2 text-sm font-medium bg-primary text-primary-foreground rounded-lg hover:bg-primary/90">
              Simpan
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
