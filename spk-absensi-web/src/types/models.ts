export interface PaginatedResponse<T> {
  data: T[]
  meta?: {
    current_page: number
    from: number | null
    last_page: number
    per_page: number
    to: number | null
    total: number
  }
}

export interface Employee {
  id: number
  nip: string
  nama: string
  departemen: string
  jabatan: string
  status_aktif: boolean
  status: 'aktif' | 'nonaktif'
}

export interface EmployeeCard {
  id: number
  employee_id: number
  rfid_uid: string
  is_active: boolean
}

export interface Fingerprint {
  id: number
  finger_id: number
  iot_device_id: number
  device_nama: string
  created_at: string
}

export interface IotDevice {
  id: number
  nama: string
  lokasi: string
  last_seen_at: string | null
  status_koneksi: 'online' | 'offline'
  plain_key?: string // Usually returned only on creation/regenerate
}

export interface AbsenceReason {
  id: number
  kode: string
  nama: string
  is_sah: boolean
}

export interface Attendance {
  id: number
  employee_id: number
  employee?: Employee
  tanggal: string
  jam_masuk: string | null
  jam_keluar: string | null
  status: 'HADIR' | 'TERLAMBAT' | 'ABSENT'
  menit_terlambat: number
  metode: 'RFID' | 'FINGERPRINT' | null
  absence_reason_id: number | null
  absence_reason?: AbsenceReason
  is_auto_generated: boolean
}

export interface ContractGroup {
  kontrak_mulai: string
  kontrak_selesai: string
  jumlah_karyawan: number
  sudah_punya_periode: boolean
  nama_grup: string
}

export interface Contract {
  id: number
  employee_id: number
  employee?: Employee
  tanggal_mulai: string
  tanggal_selesai: string
  status: 'aktif' | 'selesai'
}

export interface EvaluationPeriod {
  id: number
  kontrak_mulai: string
  kontrak_selesai: string
  tanggal_mulai: string
  tanggal_selesai: string
  status: 'berjalan' | 'selesai'
  status_evaluasi: 'belum_dihitung' | 'sebagian_dihitung' | 'sudah_dihitung'
  nama: string
  jumlah_anggota: number
  jumlah_terhitung: number
}

export interface EvaluationMember {
  employee_id: number
  nip: string
  nama: string
  departemen: string
  jabatan: string
  contract_id: number
  sudah_dihitung: boolean
  nilai_akhir: string | null
  kategori: string | null
  spk_evaluation_id: number | null
}

export interface SpkCriteria {
  id: number
  kode: string // e.g. C1, C2
  nama: string
  bobot: number // Percentage, total 100
  tipe: 'benefit' | 'cost'
}

export interface SpkEvaluation {
  id: number
  employee_id: number
  nama: string
  departemen: string
  C1: string | null
  C2: string | null
  C3: string | null
  nilai_akhir: string
  kategori: 'positif' | 'pertimbangan' | 'perhatian' | 'evaluasi_khusus'
  calculated_at: string
}

export interface TransparencyData {
  id: number
  employee: Employee
  nilai_akhir: string
  kategori: string
  rekomendasi: string
  calculated_at: string
  
  langkah_saw: {
    langkah: {
      kode: string
      nama: string
      nilai_mentah: number
      rumus_substitusi: string
      normalisasi: number
      bobot: number
      kontribusi: number
      dibatasi?: boolean
    }[]
    total_kontribusi: number
    nilai_akhir_persen: number
    kategori: string
  }
  
  data_mentah: {
    absent_tanpa_keterangan: number
    absent_sah: number
    telat_1_10: number
    telat_lebih_10: number
    total_hadir: number
    pakai_fingerprint: number
  }
  
  bukti_absensi: {
    tanggal: string
    hari: string
    jam_masuk: string | null
    metode: string | null
    status: string
    menit_terlambat: number
    jenis: string
    kriteria: string
    poin_penalti: number
  }[]

  keputusan_hrd: {
    decision: 'DIPERPANJANG' | 'TIDAK_DIPERPANJANG' | 'DITUNDA'
    catatan: string | null
    decided_at?: string
  } | null
  
  details: {
    kode: string
    nama: string
    data_mentah: Record<string, any>
    nilai_mentah: string
    rumus_substitusi: string
    nilai_normalisasi: string
    bobot: string
    kontribusi: string
    daftar_pemicu_penalti: string[]
  }[]
}

export interface RecalculateResponse extends TransparencyData {
  sebelum: {
    nilai_akhir: string
    kategori: string
    calculated_at: string
  }
}

