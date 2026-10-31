import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { 
  Users, Search, Plus, Edit2, Trash2, ChevronLeft, ChevronRight, Fingerprint, CreditCard, UserCircle, X, AlertTriangle, Loader2, ShieldAlert, CalendarDays, FileText, CreditCard as CardIcon
} from 'lucide-react'
import { AxiosError } from 'axios'

import { employeeApi } from '@/api/employees'
import type { EmployeeCard, FingerprintData } from '@/api/employees'
import { deviceApi } from '@/api/devices'
import { Pagination } from '@/components/shared/Pagination'
import { cn, formatDate, formatDateTime } from '@/lib/utils'
import type { Employee } from '@/types/models'

// ─── Zod Schema ────────────────────────────────────────────────────────────
const employeeSchema = z.object({
  nip: z.string().min(1, 'NIP wajib diisi'),
  nama: z.string().min(1, 'Nama wajib diisi'),
  departemen: z.string().min(1, 'Departemen wajib diisi'),
  jabatan: z.string().min(1, 'Jabatan wajib diisi'),
  // z.coerce.boolean() mengonversi string 'true'/'false' ke boolean dan menghasilkan
  // output type `boolean` yang kompatibel dengan zodResolver<EmployeeForm>
  status_aktif: z.coerce.boolean()
})
type EmployeeForm = z.infer<typeof employeeSchema>

const fingerprintSchema = z.object({
  device_id: z.any().refine(v => Number(v) > 0, 'Pilih device terlebih dahulu'),
  finger_id: z.string()
    .min(1, 'Finger ID wajib diisi')
    .regex(/^\d+$/, 'Finger ID harus berupa angka')
    .refine(v => Number(v) >= 1 && Number(v) <= 127, 'Finger ID harus antara 1 – 127'),
})
type FingerprintForm = z.infer<typeof fingerprintSchema>

// ─── Main Component ────────────────────────────────────────────────────────
export default function KaryawanPage() {
  const queryClient = useQueryClient()
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(10)
  const [search, setSearch] = useState('')
  const [activeSearch, setActiveSearch] = useState('')
  
  // Modals state
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [selectedEmp, setSelectedEmp] = useState<Employee | null>(null)
  
  const [isDetailOpen, setIsDetailOpen] = useState(false)
  const [detailTab, setDetailTab] = useState<'profile' | 'rfid' | 'fingerprint'>('profile')
  
  const [isDeleteOpen, setIsDeleteOpen] = useState(false)
  const [empToDelete, setEmpToDelete] = useState<Employee | null>(null)

  // ─── Queries ─────────────────────────────────────────────────────────────
  const { data, isLoading } = useQuery({
    queryKey: ['employees', page, perPage, activeSearch],
    queryFn: () => employeeApi.getAll({ page, per_page: perPage, search: activeSearch })
  })

  // ─── Handlers ────────────────────────────────────────────────────────────
  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault()
    setPage(1)
    setActiveSearch(search)
  }

  const openAdd = () => {
    setSelectedEmp(null)
    setIsFormOpen(true)
  }

  const openEdit = (emp: Employee) => {
    setSelectedEmp(emp)
    setIsFormOpen(true)
  }

  const openDetail = (emp: Employee) => {
    setSelectedEmp(emp)
    setDetailTab('profile')
    setIsDetailOpen(true)
  }

  const confirmDelete = (emp: Employee) => {
    setEmpToDelete(emp)
    setIsDeleteOpen(true)
  }

  // ─── Render ──────────────────────────────────────────────────────────────
  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Data Karyawan</h1>
          <p className="text-sm text-muted-foreground">Kelola data master karyawan dan departemen.</p>
        </div>
        <button 
          onClick={openAdd}
          className="flex items-center gap-2 px-4 py-2 bg-primary text-primary-foreground rounded-xl text-sm font-medium hover:bg-primary/90 transition-colors"
        >
          <Plus size={16} />
          Tambah Karyawan
        </button>
      </div>

      {/* Toolbar */}
      <div className="bg-card border border-border rounded-2xl p-4 shadow-sm flex flex-col sm:flex-row justify-between gap-4">
        <form onSubmit={handleSearch} className="relative max-w-sm w-full">
          <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
          <input 
            type="text" 
            placeholder="Cari NIP atau Nama..." 
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full pl-9 pr-4 py-2 rounded-lg border border-border bg-background text-sm focus:outline-none focus:ring-2 focus:ring-primary/30"
          />
        </form>
      </div>

      {/* Table */}
      <div className="bg-card border border-border rounded-2xl shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm text-left">
            <thead className="bg-muted/50 text-muted-foreground uppercase text-xs">
              <tr>
                <th className="px-6 py-4 font-semibold">Karyawan</th>
                <th className="px-6 py-4 font-semibold">Jabatan & Dept</th>
                <th className="px-6 py-4 font-semibold">Status</th>
                <th className="px-6 py-4 font-semibold text-right">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {isLoading ? (
                <tr>
                  <td colSpan={4} className="px-6 py-8 text-center text-muted-foreground">
                    <Loader2 size={24} className="animate-spin mx-auto mb-2" />
                    Memuat data...
                  </td>
                </tr>
              ) : data?.data.length === 0 ? (
                <tr>
                  <td colSpan={4} className="px-6 py-8 text-center text-muted-foreground">
                    <Users size={32} className="mx-auto mb-2 opacity-20" />
                    Tidak ada data karyawan ditemukan.
                  </td>
                </tr>
              ) : (
                data?.data.map((emp) => (
                  <tr key={emp.id} className="hover:bg-muted/30 transition-colors">
                    <td className="px-6 py-4">
                      <p className="font-semibold text-foreground">{emp.nama}</p>
                      <p className="text-xs text-muted-foreground mt-0.5">{emp.nip}</p>
                    </td>
                    <td className="px-6 py-4">
                      <p className="font-medium">{emp.jabatan}</p>
                      <p className="text-xs text-muted-foreground mt-0.5">{emp.departemen}</p>
                    </td>
                    <td className="px-6 py-4">
                      <span className={cn(
                        "inline-flex px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider rounded-md border",
                        emp.status_aktif ? "bg-emerald-100 text-emerald-700 border-emerald-200" : "bg-muted text-muted-foreground border-border"
                      )}>
                        {emp.status_aktif ? 'Aktif' : 'Nonaktif'}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-right">
                      <div className="flex items-center justify-end gap-2">
                        <button onClick={() => openDetail(emp)} className="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Detail">
                          <UserCircle size={16} />
                        </button>
                        <button onClick={() => openEdit(emp)} className="p-1.5 text-amber-600 hover:bg-amber-50 rounded-lg transition-colors" title="Edit">
                          <Edit2 size={16} />
                        </button>
                        <button onClick={() => confirmDelete(emp)} className="p-1.5 text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" title="Hapus">
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
            onPageChange={(p) => setPage(p)}
            perPage={perPage}
            onPerPageChange={(pp) => { setPerPage(pp); setPage(1) }}
          />
        )}
      </div>

      {/* ─── Modals ─────────────────────────────────────────────────────── */}
      
      {/* Form Modal */}
      {isFormOpen && (
        <EmployeeFormModal 
          employee={selectedEmp} 
          onClose={() => setIsFormOpen(false)} 
          onSuccess={() => {
            setIsFormOpen(false)
            queryClient.invalidateQueries({ queryKey: ['employees'] })
          }}
        />
      )}

      {/* Purge Confirmation */}
      {isDeleteOpen && empToDelete && (
        <PurgeConfirmModal
          employee={empToDelete}
          onCancel={() => setIsDeleteOpen(false)}
          onSuccess={() => {
            queryClient.invalidateQueries({ queryKey: ['employees'] })
            queryClient.invalidateQueries({ queryKey: ['attendance'] })
            setIsDeleteOpen(false)
          }}
        />
      )}

      {/* Detail Modal/Panel */}
      {isDetailOpen && selectedEmp && (
        <EmployeeDetailPanel 
          employee={selectedEmp}
          activeTab={detailTab}
          setTab={setDetailTab}
          onClose={() => setIsDetailOpen(false)}
        />
      )}
    </div>
  )
}

// ─── Sub Components ────────────────────────────────────────────────────────

function EmployeeFormModal({ employee, onClose, onSuccess }: { employee: Employee | null, onClose: () => void, onSuccess: () => void }) {
  const isEdit = !!employee
  const { register, handleSubmit, formState: { errors }, setError, watch, setValue } = useForm<EmployeeForm>({
    resolver: zodResolver(employeeSchema) as import('react-hook-form').Resolver<EmployeeForm>,
    defaultValues: employee ? {
      nip: employee.nip,
      nama: employee.nama,
      departemen: employee.departemen,
      jabatan: employee.jabatan,
      status_aktif: employee.status_aktif
    } : { status_aktif: true }
  })

  const mutation = useMutation({
    mutationFn: (data: EmployeeForm) => isEdit ? employeeApi.update(employee.id, data) : employeeApi.create(data),
    onSuccess,
    onError: (err: AxiosError<any>) => {
      if (err.response?.status === 422 && err.response.data.errors) {
        Object.entries(err.response.data.errors).forEach(([field, messages]: [string, unknown]) => {
          setError(field as keyof EmployeeForm, { type: 'server', message: (messages as string[])[0] })
        })
      }
    }
  })

  return (
    <ModalWrapper title={isEdit ? 'Edit Karyawan' : 'Tambah Karyawan'} onClose={onClose}>
      <form onSubmit={handleSubmit((d) => mutation.mutate(d as unknown as EmployeeForm))} className="space-y-4">
        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-1">
            <label className="text-sm font-medium">NIP</label>
            <input {...register('nip')} className={cn("w-full px-3 py-2 rounded-lg border bg-background text-sm", errors.nip && "border-destructive")} />
            {errors.nip && <p className="text-destructive text-xs">{errors.nip.message}</p>}
          </div>
          <div className="space-y-1">
            <label className="text-sm font-medium">Nama Lengkap</label>
            <input {...register('nama')} className={cn("w-full px-3 py-2 rounded-lg border bg-background text-sm", errors.nama && "border-destructive")} />
            {errors.nama && <p className="text-destructive text-xs">{errors.nama.message}</p>}
          </div>
          <div className="space-y-1">
            <label className="text-sm font-medium">Departemen</label>
            <input {...register('departemen')} className={cn("w-full px-3 py-2 rounded-lg border bg-background text-sm", errors.departemen && "border-destructive")} />
            {errors.departemen && <p className="text-destructive text-xs">{errors.departemen.message}</p>}
          </div>
          <div className="space-y-1">
            <label className="text-sm font-medium">Jabatan</label>
            <input {...register('jabatan')} className={cn("w-full px-3 py-2 rounded-lg border bg-background text-sm", errors.jabatan && "border-destructive")} />
            {errors.jabatan && <p className="text-destructive text-xs">{errors.jabatan.message}</p>}
          </div>
          <div className="space-y-1 col-span-2">
            <label className="text-sm font-medium">Status</label>
            <select
              value={watch('status_aktif') ? 'true' : 'false'}
              onChange={(e) => setValue('status_aktif', e.target.value === 'true', { shouldValidate: true })}
              className="w-full px-3 py-2 rounded-lg border border-border bg-background text-sm"
            >
              <option value="true">Aktif</option>
              <option value="false">Nonaktif</option>
            </select>
          </div>
        </div>
        
        <div className="flex justify-end gap-3 pt-4 border-t border-border mt-6">
          <button type="button" onClick={onClose} className="px-4 py-2 text-sm font-medium hover:bg-muted rounded-lg">Batal</button>
          <button type="submit" disabled={mutation.isPending} className="px-4 py-2 text-sm font-medium bg-primary text-primary-foreground rounded-lg hover:bg-primary/90 flex items-center gap-2">
            {mutation.isPending && <Loader2 size={16} className="animate-spin" />}
            Simpan
          </button>
        </div>
      </form>
    </ModalWrapper>
  )
}

function EmployeeDetailPanel({ employee, activeTab, setTab, onClose }: { employee: Employee, activeTab: string, setTab: (tab: "profile" | "rfid" | "fingerprint") => void, onClose: () => void }) {
  const queryClient = useQueryClient()
  
  // Queries
  const { data: cards } = useQuery({
    queryKey: ['employee', employee.id, 'cards'],
    queryFn: () => employeeApi.getCards(employee.id),
    enabled: activeTab === 'rfid'
  })
  
  const { data: fingerprints } = useQuery({
    queryKey: ['employee', employee.id, 'fingerprints'],
    queryFn: () => employeeApi.getFingerprints(employee.id),
    enabled: activeTab === 'fingerprint'
  })

  const { data: devicesData } = useQuery({
    queryKey: ['devices-list'],
    queryFn: () => deviceApi.getAll({ page: 1 }),
    enabled: activeTab === 'fingerprint',
    staleTime: 60_000,
  })
  const deviceList = devicesData?.data ?? []

  // Mutations
  const addCard = useMutation({
    mutationFn: (uid: string) => employeeApi.addCard(employee.id, uid),
    onSuccess: () => {
      alert('Kartu berhasil ditambahkan');
      queryClient.invalidateQueries({ queryKey: ['employee', employee.id, 'cards'] });
    },
    onError: (err: any) => {
      console.error('Add Card Error:', {
        message: err.message,
        status: err.response?.status,
        data: err.response?.data
      });
      alert('Gagal menambahkan kartu');
    }
  })

  const fpForm = useForm<FingerprintForm>({
    resolver: zodResolver(fingerprintSchema),
    defaultValues: { device_id: 0, finger_id: undefined },
  })

  const addFingerprint = useMutation({
    mutationFn: (data: FingerprintForm) => employeeApi.addFingerprint(employee.id, data),
    onSuccess: () => {
      alert('Sidik jari berhasil didaftarkan.');
      fpForm.reset();
      queryClient.invalidateQueries({ queryKey: ['employee', employee.id, 'fingerprints'] });
    },
    onError: (err: any) => {
      const msg: string = err.response?.data?.message ?? 'Gagal mendaftarkan sidik jari.';
      // Tunjukkan error duplikat di field finger_id
      if (err.response?.status === 422) {
        fpForm.setError('finger_id', { type: 'server', message: msg });
      } else {
        alert(msg);
      }
    }
  })

  const toggleCard = useMutation({
    mutationFn: (cardId: number) => employeeApi.toggleCardStatus(employee.id, cardId),
    onSuccess: (res) => {
      alert(res.message);
      queryClient.invalidateQueries({ queryKey: ['employee', employee.id, 'cards'] });
    },
    onError: (err: any) => {
      console.error('Toggle Card Error:', err.response?.data);
      alert('Gagal mengubah status kartu');
    }
  })

  const deleteFingerprint = useMutation({
    mutationFn: (fpId: number) => employeeApi.deleteFingerprint(employee.id, fpId),
    onSuccess: () => {
      alert('Sidik jari berhasil dihapus');
      queryClient.invalidateQueries({ queryKey: ['employee', employee.id, 'fingerprints'] });
    },
    onError: () => alert('Gagal menghapus sidik jari')
  })

  return (
    <div className="fixed inset-0 z-50 flex justify-end">
      <div className="absolute inset-0 bg-black/50 animate-fade-in" onClick={onClose} />
      <div className="relative w-full max-w-md bg-background h-full shadow-2xl flex flex-col animate-sidebar-slide-in" style={{ transformOrigin: 'right' }}>
        
        {/* Panel Header */}
        <div className="p-6 border-b border-border bg-card">
          <div className="flex justify-between items-start">
            <div className="flex items-center gap-4">
              <div className="w-16 h-16 rounded-full bg-primary/10 flex items-center justify-center text-primary text-2xl font-bold">
                {employee.nama.charAt(0)}
              </div>
              <div>
                <h2 className="text-xl font-bold">{employee.nama}</h2>
                <p className="text-sm text-muted-foreground">{employee.nip} • {employee.departemen}</p>
              </div>
            </div>
            <button onClick={onClose} className="p-2 text-muted-foreground hover:bg-muted rounded-full transition-colors"><X size={20} /></button>
          </div>
        </div>

        {/* Tabs */}
        <div className="flex border-b border-border">
          {[
            { id: 'profile', label: 'Profil', icon: UserCircle },
            { id: 'rfid', label: 'Kartu RFID', icon: CreditCard },
            { id: 'fingerprint', label: 'Sidik Jari', icon: Fingerprint },
          ].map(t => (
            <button 
              key={t.id} 
              onClick={() => setTab(t.id as "profile" | "rfid" | "fingerprint")}
              className={cn(
                "flex-1 flex flex-col items-center gap-1 p-3 text-sm font-medium border-b-2 transition-colors",
                activeTab === t.id ? "border-primary text-primary" : "border-transparent text-muted-foreground hover:bg-muted/30"
              )}
            >
              <t.icon size={18} />
              {t.label}
            </button>
          ))}
        </div>

        {/* Tab Content */}
        <div className="p-6 flex-1 overflow-y-auto">
          {activeTab === 'profile' && (
            <div className="space-y-4 text-sm">
              <div><p className="text-muted-foreground mb-1">Jabatan</p><p className="font-medium">{employee.jabatan}</p></div>
              <div><p className="text-muted-foreground mb-1">Status</p>
                <span className={cn("px-2 py-0.5 text-xs font-bold rounded-md border", employee.status_aktif ? "bg-emerald-100 text-emerald-700 border-emerald-200" : "bg-muted text-muted-foreground border-border")}>
                  {employee.status_aktif ? 'Aktif' : 'Nonaktif'}
                </span>
              </div>
            </div>
          )}

          {activeTab === 'rfid' && (
            <div className="space-y-4">
              {/* Form tambah kartu */}
              <form onSubmit={(e) => {
                e.preventDefault(); 
                const val = (e.target as HTMLFormElement).uid.value.trim(); 
                if (val) { addCard.mutate(val); (e.target as HTMLFormElement).reset() }
              }} className="flex gap-2">
                <input
                  name="uid"
                  placeholder="UID Kartu (contoh: 51212A66)"
                  className="flex-1 px-3 py-2 text-sm border border-border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-primary/30"
                />
                <button
                  type="submit"
                  disabled={addCard.isPending}
                  className="px-4 py-2 bg-primary text-primary-foreground text-sm rounded-lg hover:bg-primary/90 disabled:opacity-60 flex items-center gap-1.5"
                >
                  {addCard.isPending && <Loader2 size={14} className="animate-spin" />}
                  Daftar
                </button>
              </form>

              {/* Daftar kartu */}
              <div className="space-y-2">
                {cards === undefined && (
                  <div className="flex items-center justify-center py-6 text-muted-foreground">
                    <Loader2 size={20} className="animate-spin mr-2" /> Memuat kartu...
                  </div>
                )}
                {cards?.length === 0 && (
                  <p className="text-sm text-muted-foreground text-center py-6">Belum ada kartu RFID terdaftar.</p>
                )}
                {cards?.map((c: EmployeeCard) => (
                  <div key={c.id} className="p-3 border border-border rounded-xl bg-card">
                    <div className="flex items-center justify-between gap-2">
                      <div className="min-w-0">
                        <p className="font-mono text-sm font-semibold truncate">{c.uid_rfid}</p>
                        <p className="text-xs text-muted-foreground mt-0.5">Didaftarkan {formatDate(c.created_at)}</p>
                      </div>
                      <div className="flex items-center gap-2 shrink-0">
                        {/* Badge status */}
                        <span className={cn(
                          'inline-flex items-center px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide rounded-md border',
                          c.status === 'aktif'
                            ? 'bg-emerald-100 text-emerald-700 border-emerald-200'
                            : 'bg-rose-100 text-rose-600 border-rose-200'
                        )}>
                          {c.status === 'aktif' ? 'Aktif' : 'Nonaktif'}
                        </span>
                        {/* Tombol toggle */}
                        <button
                          disabled={toggleCard.isPending}
                          onClick={() => toggleCard.mutate(c.id)}
                          className={cn(
                            'text-xs font-medium px-2.5 py-1 rounded-lg disabled:opacity-50 transition-colors',
                            c.status === 'aktif'
                              ? 'text-rose-600 bg-rose-50 hover:bg-rose-100'
                              : 'text-emerald-700 bg-emerald-50 hover:bg-emerald-100'
                          )}
                        >
                          {toggleCard.isPending ? 'Proses...' : c.status === 'aktif' ? 'Nonaktifkan' : 'Aktifkan'}
                        </button>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {activeTab === 'fingerprint' && (
            <div className="space-y-4">
              {/* Instruksi */}
              <div className="bg-blue-50 border border-blue-200 text-blue-800 p-3 rounded-xl flex items-start gap-3">
                <AlertTriangle size={18} className="shrink-0 mt-0.5 text-blue-600" />
                <p className="text-xs leading-relaxed">
                  Pastikan sidik jari sudah <strong>dienroll secara fisik</strong> ke perangkat IoT terlebih dahulu, lalu catat Finger ID yang muncul di layar LCD alat dan masukkan di bawah.
                </p>
              </div>

              {/* Form daftarkan sidik jari */}
              <form onSubmit={fpForm.handleSubmit((d) => addFingerprint.mutate(d))} className="space-y-3">
                {/* Dropdown device */}
                <div className="space-y-1">
                  <label className="text-xs font-medium text-foreground">Perangkat (Device)</label>
                  <select
                    className={cn(
                      'w-full px-3 py-2 text-sm border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-primary/30',
                      fpForm.formState.errors.device_id ? 'border-destructive' : 'border-border'
                    )}
                    value={fpForm.watch('device_id') || ''}
                    onChange={(e) => fpForm.setValue('device_id', Number(e.target.value), { shouldValidate: true })}
                  >
                    <option value="">-- Pilih device --</option>
                    {deviceList.map((d) => (
                      <option key={d.id} value={d.id}>{d.nama} ({d.lokasi})</option>
                    ))}
                  </select>
                  {fpForm.formState.errors.device_id && (
                    <p className="text-destructive text-xs">{String(fpForm.formState.errors.device_id.message)}</p>
                  )}
                </div>

                {/* Input Finger ID */}
                <div className="space-y-1">
                  <label className="text-xs font-medium text-foreground">Finger ID</label>
                  <input
                    type="text"
                    inputMode="numeric"
                    pattern="[0-9]*"
                    placeholder="Contoh: 16"
                    className={cn(
                      'w-full px-3 py-2 text-sm border rounded-lg bg-background focus:outline-none focus:ring-2 focus:ring-primary/30',
                      fpForm.formState.errors.finger_id ? 'border-destructive' : 'border-border'
                    )}
                    {...fpForm.register('finger_id')}
                  />
                  {fpForm.formState.errors.finger_id ? (
                    <p className="text-destructive text-xs">{fpForm.formState.errors.finger_id.message}</p>
                  ) : (
                    <p className="text-[11px] text-muted-foreground">Lihat angka ID yang muncul di layar LCD alat setelah pendaftaran fisik selesai.</p>
                  )}
                </div>

                <button
                  type="submit"
                  disabled={addFingerprint.isPending}
                  className="w-full py-2 bg-primary text-primary-foreground text-sm rounded-lg hover:bg-primary/90 disabled:opacity-60 flex items-center justify-center gap-2"
                >
                  {addFingerprint.isPending && <Loader2 size={14} className="animate-spin" />}
                  Daftarkan
                </button>
              </form>

              {/* Daftar fingerprint */}
              <div className="space-y-2 pt-1">
                <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wide">Terdaftar</p>
                {fingerprints === undefined && (
                  <div className="flex items-center justify-center py-4 text-muted-foreground">
                    <Loader2 size={18} className="animate-spin mr-2" /> Memuat...
                  </div>
                )}
                {fingerprints?.length === 0 && (
                  <p className="text-sm text-muted-foreground text-center py-4">Belum ada sidik jari terdaftar.</p>
                )}
                {fingerprints?.map((f: FingerprintData) => (
                  <div key={f.id} className="p-3 border border-border rounded-xl flex justify-between items-center bg-card">
                    <div>
                      <p className="text-sm font-semibold">Finger ID: <span className="font-mono">{f.finger_id}</span></p>
                      <p className="text-xs text-muted-foreground">{f.device_nama} • {formatDate(f.created_at)}</p>
                    </div>
                    <button
                      disabled={deleteFingerprint.isPending}
                      onClick={() => deleteFingerprint.mutate(f.id)}
                      className="p-1.5 text-rose-600 hover:bg-rose-50 rounded-lg transition-colors disabled:opacity-50"
                      title="Hapus"
                    >
                      {deleteFingerprint.isPending ? <Loader2 size={16} className="animate-spin" /> : <Trash2 size={16} />}
                    </button>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  )
}

function PurgeConfirmModal({ employee, onCancel, onSuccess }: {
  employee: Employee
  onCancel: () => void
  onSuccess: () => void
}) {
  const mutation = useMutation({
    mutationFn: () => employeeApi.delete(employee.id, true),
    onSuccess,
    onError: (err: AxiosError<{ message: string }>) => {
      alert(err.response?.data?.message ?? 'Terjadi kesalahan saat menghapus.')
    }
  })

  const dataItems = [
    { icon: CalendarDays, label: 'Data Absensi', desc: 'Semua rekaman tap RFID/fingerprint & status kehadiran' },
    { icon: FileText,     label: 'Kontrak Kerja', desc: 'Semua riwayat kontrak karyawan' },
    { icon: FileText,     label: 'Evaluasi SPK', desc: 'Hasil SAW, detail kriteria, & keputusan HRD' },
    { icon: CardIcon,     label: 'Kartu RFID', desc: 'Semua kartu RFID yang terdaftar' },
    { icon: Fingerprint,  label: 'Sidik Jari', desc: 'Semua data sidik jari IoT' },
  ]

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/60 animate-fade-in" onClick={!mutation.isPending ? onCancel : undefined} />
      <div className="relative bg-card border border-rose-200 dark:border-rose-900 rounded-2xl w-full max-w-lg shadow-2xl animate-fade-in">
        {/* Header merah */}
        <div className="flex items-center gap-3 p-5 bg-rose-50 dark:bg-rose-950/30 border-b border-rose-200 dark:border-rose-900 rounded-t-2xl">
          <div className="p-2 bg-rose-100 dark:bg-rose-900/50 rounded-xl">
            <ShieldAlert size={22} className="text-rose-600" />
          </div>
          <div className="flex-1">
            <h3 className="font-bold text-rose-700 dark:text-rose-400">Hapus Semua Data Karyawan</h3>
            <p className="text-xs text-rose-600/80 dark:text-rose-500 mt-0.5">Tindakan ini tidak bisa dibatalkan</p>
          </div>
          <button
            onClick={onCancel}
            disabled={mutation.isPending}
            className="p-1 text-rose-400 hover:bg-rose-100 rounded-full transition-colors"
          >
            <X size={18} />
          </button>
        </div>

        <div className="p-5 space-y-4">
          {/* Identitas karyawan */}
          <div className="flex items-center gap-3 p-3 bg-muted/50 rounded-xl border border-border">
            <div className="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold text-lg shrink-0">
              {employee.nama.charAt(0)}
            </div>
            <div>
              <p className="font-semibold text-foreground">{employee.nama}</p>
              <p className="text-xs text-muted-foreground">{employee.nip} · {employee.departemen}</p>
            </div>
          </div>

          {/* Daftar data yang akan dihapus */}
          <div className="space-y-1.5">
            <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wide">Data yang akan dihapus permanen:</p>
            {dataItems.map((item, i) => (
              <div key={i} className="flex items-start gap-3 p-2.5 rounded-lg bg-rose-50/50 dark:bg-rose-950/20 border border-rose-100 dark:border-rose-900/40">
                <item.icon size={15} className="text-rose-500 shrink-0 mt-0.5" />
                <div>
                  <p className="text-sm font-medium text-rose-700 dark:text-rose-400">{item.label}</p>
                  <p className="text-xs text-rose-600/70 dark:text-rose-500/70">{item.desc}</p>
                </div>
              </div>
            ))}
          </div>

          {/* Peringatan */}
          <div className="flex items-start gap-2 p-3 bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800 rounded-xl text-amber-800 dark:text-amber-400">
            <AlertTriangle size={15} className="shrink-0 mt-0.5" />
            <p className="text-xs leading-relaxed">
              <strong>Catatan:</strong> Periode evaluasi yang dibuat bersama karyawan lain tidak ikut terhapus — hanya data milik karyawan ini.
            </p>
          </div>
        </div>

        {/* Footer */}
        <div className="flex justify-end gap-3 px-5 pb-5">
          <button
            onClick={onCancel}
            disabled={mutation.isPending}
            className="px-4 py-2 text-sm font-medium hover:bg-muted rounded-lg disabled:opacity-50 transition-colors"
          >
            Batal
          </button>
          <button
            onClick={() => mutation.mutate()}
            disabled={mutation.isPending}
            className="flex items-center gap-2 px-5 py-2 text-sm font-semibold bg-rose-600 hover:bg-rose-700 text-white rounded-lg disabled:opacity-60 transition-colors"
          >
            {mutation.isPending
              ? <><Loader2 size={15} className="animate-spin" /> Menghapus...</>
              : <><Trash2 size={15} /> Ya, Hapus Semua Data</>}
          </button>
        </div>
      </div>
    </div>
  )
}

function ModalWrapper({ title, onClose, children }: { title: string, onClose: () => void, children: React.ReactNode }) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/50 animate-fade-in" onClick={onClose} />
      <div className="relative bg-card border border-border rounded-2xl w-full max-w-lg shadow-xl animate-fade-in">
        <div className="flex items-center justify-between p-4 border-b border-border">
          <h3 className="font-semibold">{title}</h3>
          <button onClick={onClose} className="p-1 text-muted-foreground hover:bg-muted rounded-full transition-colors"><X size={18} /></button>
        </div>
        <div className="p-4">
          {children}
        </div>
      </div>
    </div>
  )
}
