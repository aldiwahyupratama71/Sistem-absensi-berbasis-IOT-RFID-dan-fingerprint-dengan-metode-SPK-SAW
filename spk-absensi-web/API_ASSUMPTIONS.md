# API.md — Dokumentasi Endpoint Backend SPK Absensi

> Base URL: `http://localhost:8000/api`
> Auth: Bearer token via Laravel Sanctum (`Authorization: Bearer {token}`)
> Format tanggal: `YYYY-MM-DD`. Format response: JSON (kecuali export yang mengembalikan file).

---

## Daftar Isi
1. [Auth](#auth)
2. [Employees](#employees)
3. [IoT Devices](#iot-devices)
4. [Holidays](#holidays)
5. [Absence Reasons](#absence-reasons)
6. [Attendance](#attendance)
7. [Contracts](#contracts)
8. [Evaluation Periods](#evaluation-periods)
9. [Evaluations](#evaluations)
10. [SPK Criteria](#spk-criteria)
11. [Dashboard](#dashboard)
12. [Exports](#exports)
13. [ESP32 (Device-Auth)](#esp32-device-auth)

---

## Auth

### `POST /api/auth/login`
| Field | Nilai |
|-------|-------|
| Auth | — (publik) |
| Body | `email`, `password` |

**Response 200:**
```json
{
  "token": "1|abc123...",
  "user": { "id": 1, "name": "Admin HRD", "email": "hrd@spk-absensi.local" }
}
```
**Response 422:** `{ "message": "The given data was invalid.", "errors": {...} }`

---

### `POST /api/auth/logout`
| Field | Nilai |
|-------|-------|
| Auth | Bearer token |

**Response 200:** `{ "message": "Berhasil logout" }`

---

### `GET /api/auth/me`
| Field | Nilai |
|-------|-------|
| Auth | Bearer token |

**Response 200:**
```json
{ "id": 1, "name": "Admin HRD", "email": "hrd@spk-absensi.local", "created_at": "..." }
```

---

## Employees

### `GET /api/employees`
| Field | Nilai |
|-------|-------|
| Auth | Bearer token |
| Query | `search` (nama/NIP), `status_aktif` (true/false), `per_page` (default 15) |

**Response 200:** Laravel Paginate
```json
{
  "data": [
    { "id": 1, "nip": "E001", "nama": "Budi Santoso", "departemen": "IT",
      "jabatan": "Backend Developer", "status_aktif": true, "status": "aktif" }
  ],
  "meta": { "current_page": 1, "last_page": 2, "per_page": 15, "total": 15 }
}
```

---

### `POST /api/employees`
| Field | Nilai |
|-------|-------|
| Auth | Bearer token |
| Body | `nip`*, `nama`*, `departemen`, `jabatan`, `status_aktif` (boolean) |

**Response 201:** Data karyawan baru.
**Response 422:** Validasi gagal (NIP duplikat, dsb).

---

### `GET /api/employees/{id}`
**Response 200:** Data karyawan tunggal.

---

### `PUT /api/employees/{id}`
| Body | `nip`, `nama`, `departemen`, `jabatan`, `status_aktif` (semua opsional / PATCH-style) |
**Response 200:** Data karyawan yang diperbarui.

---

### `DELETE /api/employees/{id}`
> Tidak menghapus fisik — mengubah `status_aktif = false`.
> Ditolak jika karyawan masih punya kartu RFID aktif.

**Response 200:** `{ "message": "Karyawan dinonaktifkan" }`
**Response 422:** `{ "message": "Karyawan masih memiliki kartu RFID aktif." }`

---

### `POST /api/employees/{id}/cards`
Tambah kartu RFID ke karyawan.
| Body | `rfid_uid`* |
**Response 201:** `{ "id": 1, "employee_id": 1, "rfid_uid": "AB:CD:EF:01", "is_active": true }`
**Response 422:** NIP nonaktif atau UID duplikat.

---

### `PATCH /api/employees/{id}/cards/{cardId}/deactivate`
Nonaktifkan kartu RFID.
**Response 200:** `{ "message": "Kartu dinonaktifkan" }`

---

### `GET /api/employees/{id}/fingerprints`
Daftar fingerprint terdaftar.
**Response 200:** Array fingerprint.

---

### `DELETE /api/employees/{id}/fingerprints/{fingerprintId}`
Hapus data fingerprint.
**Response 204:** No content.

---

### `GET /api/employees/{id}/evaluation-history`
Riwayat nilai akhir evaluasi lintas periode.
**Response 200:**
```json
[
  {
    "evaluation_period_id": 1,
    "tanggal_mulai": "2026-08-01",
    "tanggal_selesai": "2026-08-31",
    "nilai_akhir": 88.5,
    "kategori": "pertimbangan"
  }
]
```

---

## IoT Devices

### `GET /api/devices`
| Auth | Bearer token |
**Response 200:** Paginate daftar device.
```json
{
  "data": [
    { "id": 1, "nama": "RFID Reader - Pintu Utama", "lokasi": "rfid_reader_01",
      "api_key_prefix": "sk_rfid_r", "last_seen_at": "2026-08-29T14:00:00Z" }
  ]
}
```

---

### `POST /api/devices`
| Body | `nama`*, `lokasi`* |
**Response 201:**
```json
{
  "id": 3, "nama": "...", "plain_key": "sk_abc123..._randomhex"
}
```
> `plain_key` hanya muncul **sekali** saat create. Simpan segera.

---

### `GET /api/devices/{id}`
**Response 200:** Detail device + `status_koneksi` (`aktif`/`tidak_aktif`).

---

### `PUT /api/devices/{id}`
| Body | `nama`, `lokasi` |
**Response 200:** Device yang diperbarui.

---

### `DELETE /api/devices/{id}`
**Response 204:** Hapus permanen.

---

### `POST /api/devices/{id}/regenerate-key`
Regenerasi API key. Plain key baru dikembalikan sekali.
**Response 200:** `{ "plain_key": "sk_new..." }`

---

### `GET /api/devices/status`
> ⚠️ Route ini harus dipanggil **sebelum** `GET /api/devices/{id}` agar tidak diambil sebagai ID.

Daftar semua device + status koneksi real-time.
**Response 200:**
```json
[
  {
    "id": 1, "nama": "RFID Reader - Pintu Utama",
    "lokasi": "rfid_reader_01", "last_seen_at": "2026-08-29T14:00:00Z",
    "status_koneksi": "aktif"
  }
]
```
> `aktif` = `last_seen_at` ≤ 15 menit yang lalu. `tidak_aktif` = lebih dari 15 menit atau null.

---

## Holidays

### `GET /api/holidays`
| Auth | Bearer token |
**Response 200:** Paginate.
```json
{
  "data": [
    { "id": 1, "tanggal": "2026-08-17", "keterangan": "HUT Kemerdekaan RI ke-81" }
  ]
}
```

---

### `POST /api/holidays`
| Body | `tanggal`* (YYYY-MM-DD), `keterangan`* |
**Response 201:** Data libur baru.

---

### `PUT /api/holidays/{id}`
| Body | `tanggal`, `keterangan` |
**Response 200:** Data libur yang diperbarui.

---

### `DELETE /api/holidays/{id}`
**Response 204:** No content.

---

## Absence Reasons

### `GET /api/absence-reasons`
| Auth | Bearer token |
**Response 200:**
```json
[
  { "id": 1, "kode": "MARRIAGE",     "nama": "Menikah",                "is_sah": true },
  { "id": 2, "kode": "FAMILY_DEATH", "nama": "Keluarga Meninggal",     "is_sah": true },
  { "id": 3, "kode": "HOSPITALIZED", "nama": "Dirawat di Rumah Sakit", "is_sah": true },
  { "id": 4, "kode": "NO_REASON",    "nama": "Tanpa Keterangan",       "is_sah": false }
]
```

---

## Attendance

### `GET /api/attendance`
| Auth | Bearer token |
| Query | `date_from`, `date_to`, `employee_id`, `status` (HADIR/TERLAMBAT/ABSENT), `metode` (RFID/FINGERPRINT), `has_reason` (true/false), `per_page` |

**Response 200:** Laravel Paginate
```json
{
  "data": [
    {
      "id": 1,
      "employee_id": 1,
      "tanggal": "2026-08-01",
      "jam_masuk": "06:45:00",
      "jam_keluar": "16:32:00",
      "metode": "RFID",
      "status": "HADIR",
      "menit_terlambat": 0,
      "is_auto_generated": false,
      "absence_reason_id": null,
      "employee": { "id": 1, "nama": "Budi Santoso", "nip": "E001" },
      "absence_reason": null
    }
  ]
}
```
> `status` dan `menit_terlambat` adalah **read-only** (dihitung saat tap).

---

### `PATCH /api/attendance/{id}/reason`
Set alasan absen (hanya untuk baris ABSENT).
| Body | `absence_reason_id`* (integer, exists:absence_reasons) |
**Response 200:** Data attendance yang diperbarui.
**Response 422:** `{ "message": "Hanya status ABSENT yang dapat diubah alasannya" }`

---

### `GET /api/attendance/unexplained-count`
Jumlah ABSENT tanpa alasan (untuk notifikasi).
**Response 200:** `{ "count": 3 }`

---

## Contracts

### `GET /api/contracts`
| Auth | Bearer token |
| Query | `employee_id`, `status` (aktif/selesai), `per_page` |
**Response 200:** Paginate.
```json
{
  "data": [
    {
      "id": 1, "employee_id": 1,
      "tanggal_mulai": "2026-07-01", "tanggal_selesai": "2026-08-31",
      "status": "aktif"
    }
  ]
}
```

---

### `POST /api/contracts`
| Body | `employee_id`*, `tanggal_mulai`*, `tanggal_selesai`*, `status`* (aktif/selesai) |
**Response 201:** Data kontrak baru.
**Response 422:** `tanggal_selesai` harus setelah `tanggal_mulai`, atau dua kontrak aktif tumpang tindih.

---

### `GET /api/contracts/{id}`
**Response 200:** Kontrak + relasi `employee`.

---

### `PUT /api/contracts/{id}`
**Response 200:** Kontrak yang diperbarui (validasi tumpang tindih sama seperti store).

---

### `DELETE /api/contracts/{id}`
**Response 204:** No content.

---

## Evaluation Periods

### `GET /api/evaluation-periods`
| Auth | Bearer token |
| Query | `contract_id`, `per_page` |
**Response 200:** Paginate.
```json
{
  "data": [
    {
      "id": 1, "contract_id": 1,
      "tanggal_mulai": "2026-08-01", "tanggal_selesai": "2026-08-31",
      "status": "selesai", "status_evaluasi": "sudah_dihitung",
      "contract": { "id": 1, "employee_id": 1, "tanggal_mulai": "...", "tanggal_selesai": "...", "status": "aktif" }
    }
  ]
}
```

---

### `POST /api/evaluation-periods`
| Body | `contract_id`*, `tanggal_mulai`*, `tanggal_selesai`* |

> **`status` TIDAK wajib dikirim saat create.**
> Backend otomatis menetapkan `status = 'berjalan'` untuk setiap periode yang baru dibuat.
> HRD tidak perlu memilih status — logika ini dikendalikan oleh sistem, bukan input pengguna.
> Jika tetap dikirim, nilai akan **diabaikan** dan tetap diset `'berjalan'`.

**Response 201:** Periode evaluasi baru dengan `status: "berjalan"`.
**Response 422:** Harus dalam rentang kontrak, atau tumpang tindih dengan periode lain pada kontrak yang sama.

---

### `GET /api/evaluation-periods/{id}`
**Response 200:** Detail periode + kontrak + evaluasi (jika sudah dihitung).

---

### `PUT /api/evaluation-periods/{id}`
| Query | `force=true` untuk menimpa periode yang sudah dihitung |
| Body | `tanggal_mulai`, `tanggal_selesai`, `status` (semua opsional) |

> **`status` bersifat opsional saat update** (`sometimes`).
> Gunakan ini untuk mengubah periode dari `berjalan` → `selesai` secara manual oleh HRD,
> atau nanti dapat dipanggil oleh scheduled job secara otomatis.

**Response 200:** Periode yang diperbarui.
**Response 422:** `{ "message": "Periode ini sudah memiliki hasil evaluasi. Gunakan parameter force=true untuk tetap mengubah." }`

---

### `DELETE /api/evaluation-periods/{id}`
| Query | `force=true` untuk menghapus periode yang sudah dihitung |
**Response 204:** No content.
**Response 422:** Sama dengan PUT jika belum force.

---

### `POST /api/evaluation-periods/{id}/calculate`
Jalankan perhitungan SAW untuk periode ini.
**Response 200:**
```json
{
  "message": "Kalkulasi berhasil",
  "summary": {
    "jumlah_karyawan": 1,
    "distribusi_kategori": { "positif": 1 },
    "warnings": ["Ada 1 hari ABSENT tanpa alasan/tidak sah"]
  }
}
```

---

### `GET /api/evaluation-periods/{id}/results`
Daftar hasil evaluasi periode (paginate, urut nilai_akhir DESC).
| Query | `kategori` (positif/pertimbangan/perhatian/evaluasi_khusus), `search` (nama karyawan), `per_page` |

**Response 200:** Paginate
```json
{
  "data": [
    {
      "id": 1, "employee_id": 1, "nama": "Budi Santoso",
      "departemen": "IT",
      "C1": 100.0, "C2": 90.0, "C3": 100.0,
      "nilai_akhir": 96.5,
      "kategori": "positif",
      "calculated_at": "2026-08-31T10:00:00Z"
    }
  ]
}
```

---

### `GET /api/evaluation-periods/{id}/export?format=pdf|xlsx`
Download laporan evaluasi dalam format PDF atau Excel.
| Query | `format` = `pdf` atau `xlsx` |

**Response 200:** File download (Content-Disposition: attachment).
- PDF: A4 landscape, judul, periode, bobot snapshot, tabel hasil, legend 4 kategori, catatan rekomendasi.
- XLSX: 2 sheet — "Hasil" (ringkasan) dan "Rincian" (per kriteria per karyawan).
- Nama file: `laporan-evaluasi-{YYYYMMDD}-{YYYYMMDD}-{today}.{ext}`

**Response 422:** `{ "message": "Format harus pdf atau xlsx." }`

---

## Evaluations

### `GET /api/evaluations/{id}`
Detail transparansi evaluasi lengkap.
**Response 200:**
```json
{
  "id": 1,
  "employee": { "id": 1, "nama": "Budi Santoso", "nip": "E001", "departemen": "IT" },
  "nilai_akhir": 96.5,
  "kategori": "positif",
  "rekomendasi": "Performa sangat baik. Direkomendasikan untuk apresiasi atau bonus.",
  "calculated_at": "2026-08-31T10:00:00Z",
  "details": [
    {
      "kode": "C1",
      "nama": "Kehadiran",
      "data_mentah": {
        "absent_tanpa_keterangan": 0,
        "absent_sah": 1,
        "daftar_tanggal_pemicu_penalti": []
      },
      "nilai_mentah": 100.0,
      "rumus_substitusi": "MAX(0, 100 - 0 * 60)",
      "nilai_normalisasi": 1.0,
      "bobot": 0.5,
      "kontribusi": 0.5,
      "daftar_pemicu_penalti": []
    },
    {
      "kode": "C2",
      "nama": "Ketepatan Waktu",
      "data_mentah": {
        "telat_1_10_menit": 1,
        "telat_lebih_10_menit": 0,
        "daftar_tanggal_pemicu_penalti": ["2026-08-15 (7 mnt)"]
      },
      "nilai_mentah": 90.0,
      "rumus_substitusi": "MAX(0, 100 - (1*10 + 0*40))",
      "nilai_normalisasi": 0.9,
      "bobot": 0.35,
      "kontribusi": 0.315,
      "daftar_pemicu_penalti": ["2026-08-15 (7 mnt)"]
    },
    {
      "kode": "C3",
      "nama": "Kepatuhan ID Card",
      "data_mentah": {
        "total_hadir": 21,
        "pakai_fingerprint": 0,
        "daftar_tanggal_pemicu_penalti": []
      },
      "nilai_mentah": 100.0,
      "rumus_substitusi": "(21 - 0) / 21 * 100",
      "nilai_normalisasi": 1.0,
      "bobot": 0.15,
      "kontribusi": 0.15,
      "daftar_pemicu_penalti": []
    }
  ],
  "keputusan_hrd": null
}
```

---

### `PUT /api/evaluations/{id}/decision`
Simpan/update keputusan HRD.
| Body | `decision`* (string), `catatan` (string, nullable) |

**Nilai `decision` yang disarankan:** `DIPERPANJANG`, `TIDAK_DIPERPANJANG`, `DITUNDA`

**Response 200:**
```json
{
  "message": "Keputusan berhasil disimpan",
  "data": {
    "id": 1, "spk_evaluation_id": 1,
    "decision": "DIPERPANJANG",
    "catatan": "Pertahankan dan berikan apresiasi.",
    "decided_by": 1,
    "decided_at": "2026-08-31T11:00:00Z"
  }
}
```

---

### `GET /api/evaluations/{id}/export`
Download PDF rincian evaluasi individual per karyawan.
**Response 200:** File PDF download.
- Nama file: `rincian-evaluasi-{nama-slug}-{YYYYMMDD}-{YYYYMMDD}-{today}.pdf`

---

## SPK Criteria

### `GET /api/criteria`
Daftar kriteria SAW beserta bobot yang aktif.
| Auth | Bearer token |

**Response 200:**
```json
[
  { "id": 1, "kode": "C1", "nama": "Kehadiran",         "tipe": "benefit", "bobot": 50.00 },
  { "id": 2, "kode": "C2", "nama": "Ketepatan Waktu",   "tipe": "benefit", "bobot": 35.00 },
  { "id": 3, "kode": "C3", "nama": "Kepatuhan ID Card", "tipe": "benefit", "bobot": 15.00 }
]
```
> Bobot tersimpan dalam skala 0–100. Backend membagi 100 saat normalisasi.

---

## Dashboard

### `GET /api/dashboard`
| Auth | Bearer token |
| Query | `period_id` (integer, opsional) |

**Response 200:**
```json
{
  "kartu": {
    "total_karyawan_aktif": 15,
    "tanggal_referensi": "2026-08-29",
    "tepat_waktu": 12,
    "terlambat": 2,
    "tidak_hadir": 1,
    "absen_fingerprint": 3
  },
  "rekap_kehadiran": {
    "tepat_waktu": 240,
    "terlambat": 32,
    "tidak_hadir": 18,
    "total": 290
  },
  "rata_rata_kriteria": {
    "C1": 82.5,
    "C2": 76.3,
    "C3": 91.0
  },
  "distribusi_kategori": {
    "positif":          { "jumlah": 5, "persen": 33.3 },
    "pertimbangan":     { "jumlah": 4, "persen": 26.7 },
    "perhatian":        { "jumlah": 3, "persen": 20.0 },
    "evaluasi_khusus":  { "jumlah": 3, "persen": 20.0 }
  },
  "peringatan": {
    "absen_tanpa_alasan": 2,
    "perangkat_tidak_aktif": 0
  },
  "periode": {
    "id": 1,
    "tanggal_mulai": "2026-08-01",
    "tanggal_selesai": "2026-08-31",
    "status": "selesai",
    "status_evaluasi": "sudah_dihitung",
    "kontrak": {
      "id": 1, "employee_id": 1,
      "tanggal_mulai": "2026-07-01", "tanggal_selesai": "2026-08-31", "status": "aktif"
    }
  }
}
```
> Jika `period_id` tidak diisi:
> - `rekap_kehadiran` = 30 hari terakhir
> - `rata_rata_kriteria` = `null` untuk semua
> - `distribusi_kategori` = semua 0
> - `periode` = `null`

---

### `GET /api/dashboard/attendance-trend?range=7|30`
Tren kehadiran harian untuk grafik garis.
| Query | `range` = `7` atau `30` (default 7) |

**Response 200:**
```json
[
  { "tanggal": "2026-08-23", "hadir": 12, "terlambat": 2, "tidak_hadir": 1 },
  { "tanggal": "2026-08-24", "hadir": 0,  "terlambat": 0, "tidak_hadir": 0 }
]
```
> Hari tanpa data tetap disertakan dengan nilai 0 agar grafik kontinu.

---

### `GET /api/dashboard/recent-attendance?limit=10`
Tap terbaru (max 50).
| Query | `limit` (default 10, max 50) |

**Response 200:**
```json
[
  {
    "id": 350, "employee_id": 3,
    "tanggal": "2026-08-29", "jam_masuk": "06:52:00", "jam_keluar": "16:15:00",
    "status": "HADIR", "metode": "RFID", "menit_terlambat": 0,
    "employee": { "id": 3, "nama": "Ahmad Fauzi", "nip": "E003" }
  }
]
```

---

## Exports

> Lihat detail endpoint di bagian [Evaluation Periods](#evaluation-periods) dan [Evaluations](#evaluations).

| Method | Path | Format | Keterangan |
|--------|------|--------|------------|
| GET | `/api/evaluation-periods/{id}/export?format=pdf` | application/pdf | Laporan periode |
| GET | `/api/evaluation-periods/{id}/export?format=xlsx` | application/vnd.openxmlformats-officedocument.spreadsheetml.sheet | Excel 2 sheet |
| GET | `/api/evaluations/{id}/export` | application/pdf | Rincian per karyawan |

---

## ESP32 (Device-Auth)

> ⚠️ **Frontend TIDAK boleh memanggil endpoint ini.**
> Autentikasi: Header `X-Device-Key: {prefix}.{sha256_hash}` — bukan Bearer token.

### `POST /api/devices/{device}/fingerprints`
Daftarkan fingerprint ID baru dari perangkat.
| Header | `X-Device-Key: {prefix}.{sha256}` |
| Body | `fingerprint_id`*, `employee_id`* |
**Response 201:** Data fingerprint baru.

### `POST /api/attendance/tap` *(TODO — belum diimplementasi)*
Kirim data tap dari ESP32.
| Header | `X-Device-Key: {prefix}.{sha256}` |
| Body | `rfid_uid` atau `fingerprint_id`, timestamp |

---

## Aturan Bisnis Terimplementasi

| Aturan | Status | Lokasi |
|--------|--------|--------|
| Jam masuk 07:00 — status dari jam masuk bukan metode | ✅ | `AttendanceTapService` |
| RFID: tanpa penalti C3 | ✅ | `SpkCalculationService` (metode RFID tidak dihitung) |
| Fingerprint: penalti ringan di C3 | ✅ | `SpkCalculationService` (metode FINGERPRINT → pakaiFingerprint++) |
| Alasan sah (Marriage/Family/RS) tidak dihitung pelanggaran | ✅ | `SpkCalculationService` (cek `is_sah`) |
| "Tanpa Keterangan" tidak sah | ✅ | `AbsenceReason.is_sah = false` (kode NO_REASON) |
| Bobot C1=50%, C2=35%, C3=15% | ✅ | `SpkCriteriaSeeder`, `spk_criteria` table |
| 4 kategori (Positif/Pertimbangan/Perhatian/Evaluasi Khusus) | ✅ | `SawCalculator::categorize()` + slug mapping |
| ABSENT auto-generated oleh scheduler | ✅ | `attendance:mark-absent` command |
| Periode evaluasi dalam batas kontrak | ✅ | `EvaluationPeriodController::store/update` |
| Tidak ada logika SAW di frontend | ✅ | Semua kalkulasi di `SawCalculator` + `SpkCalculationService` |
| Nilai akhir dalam persen | ✅ | `SawCalculator::finalScore()` × 100 |
| Label kategori tidak "Diterima/Ditolak" | ✅ | Enum: positif/pertimbangan/perhatian/evaluasi_khusus |

---

## Kategori & Rentang

| Kategori | Slug DB | Rentang Nilai | Warna |
|----------|---------|---------------|-------|
| Positif | `positif` | 90–100 | Hijau |
| Perlu Pertimbangan | `pertimbangan` | 80–89 | Kuning |
| Perlu Perhatian | `perhatian` | 70–79 | Oranye |
| Evaluasi Khusus | `evaluasi_khusus` | < 70 | Merah |
