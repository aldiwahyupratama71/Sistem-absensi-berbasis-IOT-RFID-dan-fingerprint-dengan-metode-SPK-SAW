import type { 
  Employee, 
  IotDevice, 
  Attendance, 
  Contract, 
  EvaluationPeriod, 
  SpkCriteria, 
  SpkEvaluation, 
  AbsenceReason
} from '@/types/models'

const DEPARTMENTS = ['IT', 'HRD', 'Finance', 'Marketing', 'Operasional']
const POSITIONS = ['Manager', 'Staff', 'Supervisor']

export const employees: Employee[] = Array.from({ length: 15 }).map((_, i) => ({
  id: i + 1,
  nip: `EMP${String(i + 1).padStart(3, '0')}`,
  nama: `Karyawan ${i + 1}`,
  email: `karyawan${i + 1}@spk.test`,
  phone: `0812345678${String(i).padStart(2, '0')}`,
  departemen: DEPARTMENTS[i % DEPARTMENTS.length],
  jabatan: POSITIONS[i % POSITIONS.length],
  status_aktif: true,
  status: 'aktif',
}))

export const devices: IotDevice[] = [
  {
    id: 1,
    nama: 'RFID Reader Pintu Utama',
    lokasi: 'Lobby',
    status_koneksi: 'online' as const,
    last_seen_at: new Date().toISOString(),
    plain_key: 'sk_test_********************'
  },
  {
    id: 2,
    nama: 'Fingerprint Ruang Karyawan',
    lokasi: 'Ruang Staff',
    status_koneksi: 'online' as const,
    last_seen_at: new Date().toISOString(),
    plain_key: 'sk_test_********************'
  }
]

export const contracts: Contract[] = [
  {
    id: 1,
    employee_id: 1,
    tanggal_mulai: '2023-01-01',
    tanggal_selesai: '2024-01-01',
    status: 'selesai'
  },
  {
    id: 2,
    employee_id: 1,
    tanggal_mulai: '2024-01-02',
    tanggal_selesai: '2025-01-02',
    status: 'aktif'
  },
  {
    id: 3,
    employee_id: 2,
    tanggal_mulai: '2024-02-01',
    tanggal_selesai: '2025-02-01',
    status: 'aktif'
  }
]

export const absence_reasons: AbsenceReason[] = [
  { id: 1, kode: 'SAKIT', nama: 'Sakit', is_sah: true },
  { id: 2, kode: 'CUTI', nama: 'Cuti', is_sah: true },
  { id: 3, kode: 'IZIN', nama: 'Izin', is_sah: true },
  { id: 4, kode: 'ALPHA', nama: 'Tanpa Keterangan', is_sah: false }
]

export const attendances: Attendance[] = []
let attendanceId = 1
const today = new Date()

employees.forEach((emp) => {
  for (let i = 30; i >= 0; i--) {
    const d = new Date(today)
    d.setDate(d.getDate() - i)
    if (d.getDay() === 0 || d.getDay() === 6) continue

    const rand = Math.random()
    let status: 'HADIR' | 'TERLAMBAT' | 'ABSENT' = 'HADIR'
    let jam_masuk: string | null = '06:50:00'
    let jam_keluar: string | null = '17:05:00'
    let menit_terlambat = 0
    let absence_reason_id = null

    if (rand < 0.1) {
      status = 'ABSENT'
      jam_masuk = null
      jam_keluar = null
      absence_reason_id = Math.random() > 0.5 ? 1 : 4
    } else if (rand < 0.3) {
      status = 'TERLAMBAT'
      const lateMins = Math.floor(Math.random() * 60) + 1
      menit_terlambat = lateMins
      const inHour = 7 + Math.floor(lateMins / 60)
      const inMin = lateMins % 60
      jam_masuk = `${String(inHour).padStart(2, '0')}:${String(inMin).padStart(2, '0')}:00`
    }

    attendances.push({
      id: attendanceId++,
      employee_id: emp.id,
      tanggal: d.toISOString().split('T')[0],
      jam_masuk,
      jam_keluar,
      status,
      menit_terlambat,
      metode: jam_masuk ? (Math.random() > 0.5 ? 'RFID' : 'FINGERPRINT') : null,
      absence_reason_id,
      is_auto_generated: !jam_masuk,
    })
  }
})

export const periods: EvaluationPeriod[] = [
  { id: 1, tanggal_mulai: '2024-01-01', tanggal_selesai: '2024-03-31', status_evaluasi: 'sudah_dihitung', status: 'selesai', kontrak_mulai: '2024-01-01', kontrak_selesai: '2024-12-31', nama: 'Grup 2024-01-01', jumlah_anggota: 15, jumlah_terhitung: 15 },
  { id: 2, tanggal_mulai: '2024-04-01', tanggal_selesai: '2024-06-30', status_evaluasi: 'belum_dihitung', status: 'berjalan', kontrak_mulai: '2024-01-01', kontrak_selesai: '2024-12-31', nama: 'Grup 2024-01-01', jumlah_anggota: 15, jumlah_terhitung: 0 },
]

export const criteria: SpkCriteria[] = [
  { id: 1, kode: 'C1', nama: 'Kehadiran', bobot: 50, tipe: 'benefit' },
  { id: 2, kode: 'C2', nama: 'Ketepatan Waktu', bobot: 35, tipe: 'benefit' },
  { id: 3, kode: 'C3', nama: 'Kepatuhan ID Card', bobot: 15, tipe: 'benefit' },
]

export let evaluations: SpkEvaluation[] = []

export const generateSPKForPeriod = (periodId: number) => {
  const newEvals: SpkEvaluation[] = []

  const rawData = employees.map(emp => {
    const empAtt = attendances.filter(a => a.employee_id === emp.id)
    const totalHadir = empAtt.filter(a => a.status === 'HADIR' || a.status === 'TERLAMBAT').length
    const totalLateMins = empAtt.reduce((sum, a) => sum + (a.menit_terlambat || 0), 0)
    const totalAlpha = empAtt.filter(a => a.status === 'ABSENT' && a.absence_reason_id === 4).length
    
    // Very simplified mockup scoring:
    const finalScore = Math.max(0, 100 - (totalAlpha * 5 + (totalLateMins > 0 ? 10 : 0)))
    
    return {
      emp,
      finalScore
    }
  })

  rawData.sort((a, b) => b.finalScore - a.finalScore)

  rawData.forEach((data) => {
    const evalId = newEvals.length + 1

    let kategori: any = 'positif'
    if (data.finalScore < 70) kategori = 'evaluasi_khusus'
    else if (data.finalScore < 80) kategori = 'perhatian'
    else if (data.finalScore < 90) kategori = 'pertimbangan'

    newEvals.push({
      id: evalId,
      employee_id: data.emp.id,
      nama: data.emp.nama,
      departemen: data.emp.departemen,
      nilai_akhir: String(data.finalScore),
      C1: null,
      C2: null,
      C3: null,
      kategori,
      calculated_at: new Date().toISOString()
    })
  })

  return { newEvals }
}

const initialSpk = generateSPKForPeriod(1)
evaluations = initialSpk.newEvals
