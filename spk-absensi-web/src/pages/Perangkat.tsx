import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { 
  Monitor, Search, Plus, Edit2, Trash2, X, AlertTriangle, Key, Copy, Check, Loader2
} from 'lucide-react'
import { AxiosError } from 'axios'
import { formatDistanceToNow } from 'date-fns'
import { id } from 'date-fns/locale'

import { deviceApi } from '@/api/devices'
import { cn, formatDateTime } from '@/lib/utils'
import type { IotDevice } from '@/types/models'

const deviceSchema = z.object({
  nama: z.string().min(1, 'Nama perangkat wajib diisi'),
  lokasi: z.string().min(1, 'Lokasi wajib diisi'),
})
type DeviceForm = z.infer<typeof deviceSchema>

export default function PerangkatPage() {
  const queryClient = useQueryClient()
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [selectedDevice, setSelectedDevice] = useState<IotDevice | null>(null)
  
  const [isDeleteOpen, setIsDeleteOpen] = useState(false)
  const [deviceToDelete, setDeviceToDelete] = useState<IotDevice | null>(null)

  const [apiKeyModal, setApiKeyModal] = useState<{ isOpen: boolean, key: string }>({ isOpen: false, key: '' })

  const { data, isLoading } = useQuery({
    queryKey: ['devices', page],
    queryFn: () => deviceApi.getAll({ page }),
    refetchInterval: 10000,
  })

  // Filter local search
  const filteredData = data?.data.filter(d => 
    d.nama.toLowerCase().includes(search.toLowerCase()) || 
    d.lokasi.toLowerCase().includes(search.toLowerCase())
  )

  const confirmDelete = (device: IotDevice) => {
    setDeviceToDelete(device)
    setIsDeleteOpen(true)
  }

  const handleRegenerate = async (id: number) => {
    if (!confirm('Regenerate API Key akan membuat key lama tidak bisa digunakan. Lanjutkan?')) return
    try {
      const res = await deviceApi.regenerateKey(id)
      setApiKeyModal({ isOpen: true, key: res.api_key || '' })
      queryClient.invalidateQueries({ queryKey: ['devices'] })
    } catch (err) {
      alert('Gagal regenerate key.')
    }
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Perangkat IoT</h1>
          <p className="text-sm text-muted-foreground">Kelola reader RFID dan Fingerprint.</p>
        </div>
        <button 
          onClick={() => { setSelectedDevice(null); setIsFormOpen(true) }}
          className="flex items-center gap-2 px-4 py-2 bg-primary text-primary-foreground rounded-xl text-sm font-medium hover:bg-primary/90 transition-colors"
        >
          <Plus size={16} />
          Tambah Perangkat
        </button>
      </div>

      {/* Toolbar */}
      <div className="bg-card border border-border rounded-2xl p-4 shadow-sm flex flex-col sm:flex-row justify-between gap-4">
        <div className="relative max-w-sm w-full">
          <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
          <input 
            type="text" 
            placeholder="Cari perangkat..." 
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full pl-9 pr-4 py-2 rounded-lg border border-border bg-background text-sm focus:outline-none focus:ring-2 focus:ring-primary/30"
          />
        </div>
      </div>

      {/* Table */}
      <div className="bg-card border border-border rounded-2xl shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm text-left">
            <thead className="bg-muted/50 text-muted-foreground uppercase text-xs">
              <tr>
                <th className="px-6 py-4 font-semibold">Perangkat</th>
                <th className="px-6 py-4 font-semibold">Lokasi</th>
                <th className="px-6 py-4 font-semibold">Status Koneksi</th>
                <th className="px-6 py-4 font-semibold">Terakhir Online</th>
                <th className="px-6 py-4 font-semibold text-right">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {isLoading ? (
                <tr>
                  <td colSpan={5} className="px-6 py-8 text-center text-muted-foreground">
                    <Loader2 size={24} className="animate-spin mx-auto mb-2" />
                    Memuat data...
                  </td>
                </tr>
              ) : filteredData?.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-6 py-8 text-center text-muted-foreground">
                    <Monitor size={32} className="mx-auto mb-2 opacity-20" />
                    Tidak ada perangkat.
                  </td>
                </tr>
              ) : (
                filteredData?.map((dev) => (
                  <tr key={dev.id} className="hover:bg-muted/30 transition-colors">
                    <td className="px-6 py-4">
                      <p className="font-semibold text-foreground">{dev.nama}</p>
                    </td>
                    <td className="px-6 py-4 font-medium">{dev.lokasi}</td>
                    <td className="px-6 py-4">
                      <div className="flex items-center gap-2">
                        <div className={cn("w-2 h-2 rounded-full", dev.status_koneksi === 'online' ? "bg-emerald-500" : "bg-zinc-400")} />
                        <span className={cn("text-xs font-semibold", dev.status_koneksi === 'online' ? "text-emerald-600" : "text-zinc-600")}>
                          {dev.status_koneksi === 'online' ? 'Online' : 'Offline'}
                        </span>
                      </div>
                    </td>
                    <td className="px-6 py-4 text-xs text-muted-foreground">
                      {dev.last_seen_at ? (
                        <div>
                          <p>{formatDateTime(dev.last_seen_at)}</p>
                          <p className="text-[11px] mt-0.5 text-muted-foreground/70">
                            {formatDistanceToNow(new Date(dev.last_seen_at), { addSuffix: true, locale: id })}
                          </p>
                        </div>
                      ) : (
                        <span className="italic">Belum pernah terhubung</span>
                      )}
                    </td>
                    <td className="px-6 py-4 text-right">
                      <div className="flex items-center justify-end gap-2">
                        <button onClick={() => handleRegenerate(dev.id)} className="p-1.5 text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" title="Regenerate API Key">
                          <Key size={16} />
                        </button>
                        <button onClick={() => { setSelectedDevice(dev); setIsFormOpen(true) }} className="p-1.5 text-amber-600 hover:bg-amber-50 rounded-lg transition-colors" title="Edit">
                          <Edit2 size={16} />
                        </button>
                        <button onClick={() => confirmDelete(dev)} className="p-1.5 text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" title="Hapus">
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
      </div>

      {/* Modals */}
      {isFormOpen && (
        <DeviceFormModal 
          device={selectedDevice} 
          onClose={() => setIsFormOpen(false)} 
          onSuccess={(apiKey) => {
            setIsFormOpen(false)
            queryClient.invalidateQueries({ queryKey: ['devices'] })
            if (apiKey) setApiKeyModal({ isOpen: true, key: apiKey })
          }}
        />
      )}

      {isDeleteOpen && deviceToDelete && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="absolute inset-0 bg-black/50" onClick={() => setIsDeleteOpen(false)} />
          <div className="relative bg-card border border-border rounded-2xl w-full max-w-sm shadow-xl p-6">
            <h3 className="font-semibold text-lg mb-2">Hapus Perangkat</h3>
            <p className="text-sm text-muted-foreground mb-6">Yakin ingin menghapus {deviceToDelete.nama}?</p>
            <div className="flex justify-end gap-3">
              <button onClick={() => setIsDeleteOpen(false)} className="px-4 py-2 text-sm font-medium hover:bg-muted rounded-lg">Batal</button>
              <button 
                onClick={async () => {
                  await deviceApi.delete(deviceToDelete.id)
                  queryClient.invalidateQueries({ queryKey: ['devices'] })
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

      {apiKeyModal.isOpen && (
        <ApiKeyModal apiKey={apiKeyModal.key} onClose={() => setApiKeyModal({ isOpen: false, key: '' })} />
      )}
    </div>
  )
}

// ─── Sub Components ────────────────────────────────────────────────────────

function DeviceFormModal({ device, onClose, onSuccess }: { device: IotDevice | null, onClose: () => void, onSuccess: (apiKey?: string) => void }) {
  const isEdit = !!device
  const { register, handleSubmit, formState: { errors }, setError } = useForm<DeviceForm>({
    resolver: zodResolver(deviceSchema),
    defaultValues: device ? { nama: device.nama, lokasi: device.lokasi } : undefined
  })

  const mutation = useMutation({
    mutationFn: (data: DeviceForm) => isEdit ? deviceApi.update(device.id, data) : deviceApi.create(data),
    onSuccess: (res: any) => onSuccess(res.api_key || ''),
    onError: (err: AxiosError<any>) => {
      if (err.response?.status === 422 && err.response.data.errors) {
        Object.entries(err.response.data.errors).forEach(([field, messages]: [string, unknown]) => {
          setError(field as keyof DeviceForm, { type: 'server', message: (messages as string[])[0] })
        })
      }
    }
  })

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/50 animate-fade-in" onClick={onClose} />
      <div className="relative bg-card border border-border rounded-2xl w-full max-w-md shadow-xl animate-fade-in">
        <div className="flex items-center justify-between p-4 border-b border-border">
          <h3 className="font-semibold">{isEdit ? 'Edit Perangkat' : 'Tambah Perangkat'}</h3>
          <button onClick={onClose} className="p-1 text-muted-foreground hover:bg-muted rounded-full"><X size={18} /></button>
        </div>
        <form onSubmit={handleSubmit((d) => mutation.mutate(d))} className="p-4 space-y-4">
          <div className="space-y-1">
            <label className="text-sm font-medium">Nama Perangkat</label>
            <input {...register('nama')} className={cn("w-full px-3 py-2 rounded-lg border bg-background text-sm", errors.nama && "border-destructive")} placeholder="Contoh: Reader Lantai 1" />
            {errors.nama && <p className="text-destructive text-xs">{errors.nama.message}</p>}
          </div>
          <div className="space-y-1">
            <label className="text-sm font-medium">Lokasi</label>
            <input {...register('lokasi')} className={cn("w-full px-3 py-2 rounded-lg border bg-background text-sm", errors.lokasi && "border-destructive")} placeholder="Contoh: Pintu Masuk Utama" />
            {errors.lokasi && <p className="text-destructive text-xs">{errors.lokasi.message}</p>}
          </div>

          {!isEdit && (
            <div className="bg-amber-50 border border-amber-200 text-amber-800 p-3 rounded-lg flex items-start gap-2 text-sm">
              <AlertTriangle size={16} className="shrink-0 mt-0.5" />
              <p>API Key akan ditampilkan sekali setelah perangkat disimpan. Pastikan segera menyalinnya.</p>
            </div>
          )}

          <div className="flex justify-end gap-3 pt-4 border-t border-border mt-2">
            <button type="button" onClick={onClose} className="px-4 py-2 text-sm font-medium hover:bg-muted rounded-lg">Batal</button>
            <button type="submit" disabled={mutation.isPending} className="px-4 py-2 text-sm font-medium bg-primary text-primary-foreground rounded-lg hover:bg-primary/90 flex items-center gap-2">
              {mutation.isPending && <Loader2 size={16} className="animate-spin" />}
              Simpan
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

function ApiKeyModal({ apiKey, onClose }: { apiKey: string, onClose: () => void }) {
  const [copied, setCopied] = useState(false)
  const handleCopy = () => {
    navigator.clipboard.writeText(apiKey)
    setCopied(true)
    setTimeout(() => setCopied(false), 2000)
  }

  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/50" />
      <div className="relative bg-card border border-border rounded-2xl w-full max-w-md shadow-2xl p-6 text-center animate-fade-in">
        <div className="w-16 h-16 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center mx-auto mb-4">
          <Key size={32} />
        </div>
        <h3 className="text-xl font-bold mb-2">API Key Perangkat</h3>
        <p className="text-sm text-muted-foreground mb-6">
          API Key ini hanya ditampilkan <strong>SEKALI SAJA</strong>. Segera salin dan simpan di firmware perangkat IoT (NodeMCU/ESP32) Anda.
        </p>
        
        <div className="flex items-center gap-2 bg-muted p-2 rounded-xl mb-6 border border-border">
          <code className="flex-1 text-sm font-mono truncate px-2 select-all text-left">{apiKey}</code>
          <button 
            onClick={handleCopy} 
            className="p-2 bg-background border border-border rounded-lg shadow-sm hover:bg-accent transition-colors text-foreground"
          >
            {copied ? <Check size={16} className="text-emerald-500" /> : <Copy size={16} />}
          </button>
        </div>

        <button onClick={onClose} className="w-full py-2.5 bg-primary text-primary-foreground rounded-xl text-sm font-medium hover:bg-primary/90">
          Saya Sudah Menyimpannya
        </button>
      </div>
    </div>
  )
}
