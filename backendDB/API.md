# API.md — Dokumentasi Endpoint Backend SPK Absensi

> Base URL: `http://localhost:8000/api`
> Auth: Bearer token via Laravel Sanctum (`Authorization: Bearer {token}`)
> Format input tanggal: `YYYY-MM-DD`. Format response: JSON (kecuali export yang mengembalikan file).
> **Format datetime response: ISO 8601 UTC** — semua field `*_at` mengembalikan `2026-08-29T14:00:00.000000Z`.

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

## Format Tanggal & Waktu

> **Aturan global:** SEMUA field bertipe datetime di seluruh API (field yang berakhiran `_at`) mengembalikan format **ISO 8601 UTC** tanpa kecuali.
>
> | Tipe | Format output | Contoh |
> |------|--------------|--------|
> | Datetime (`*_at`) | `YYYY-MM-DDTHH:mm:ss.ffffffZ` | `2026-08-29T07:00:00.000000Z` |
> | Tanggal saja (`tanggal_*`) | `YYYY-MM-DD` | `2026-08-29` |
> | Waktu saja (`jam_*`) | `HH:mm:ss` | `07:00:00` |
>
> Field `last_seen_at` pada perangkat IoT dapat bernilai `null` jika perangkat belum pernah melakukan tap.

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
> **Hapus permanen.** Hanya diizinkan jika karyawan **tidak** memiliki riwayat attendance, kontrak, atau evaluasi SPK.
> Untuk menonaktifkan tanpa menghapus data, gunakan `PUT /api/employees/{id}` dengan `status_aktif: false`.

**Urutan validasi (ditolak 422 jika):**
1. Karyawan memiliki riwayat attendance / kontrak / evaluasi SPK.
2. Karyawan masih memiliki kartu RFID dengan status `aktif`.

**Response 200:** `{ "message": "Karyawan berhasil dihapus secara permanen." }`
**Response 422 (riwayat):** `{ "message": "Karyawan memiliki riwayat absensi/kontrak/evaluasi dan tidak bisa dihapus permanen. Nonaktifkan lewat menu Edit." }`
**Response 422 (kartu aktif):** `{ "message": "Nonaktifkan semua kartu RFID karyawan ini terlebih dahulu sebelum menghapus." }`

---

### `GET /api/employees/{id}/cards`
Daftar kartu RFID milik karyawan.
**Response 200:**
```json
{
  "data": [
    {
      "id": 1,
      "uid_rfid": "AB:CD:EF:01",
      "status": "aktif",
      "created_at": "2024-05-20T10:00:00.000000Z"
    }
  ]
}
```

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
Daftar fingerprint terdaftar milik karyawan.
| Auth | Bearer token |

**Response 200:**
```json
{
  "data": [
    {
      "id": 1,
      "finger_id": "100",
      "iot_device_id": 2,
      "device_nama": "ESP32 Fingerprint",
      "created_at": "2026-09-30T07:00:00.000000Z"
    }
  ]
}
```

---

### `POST /api/employees/{id}/fingerprints`
Daftarkan fingerprint baru untuk karyawan (via HRD, bukan dari ESP32).
| Auth | Bearer token |
| Body | `device_id`* (integer, id perangkat fingerprint), `finger_id`* (string, slot ID di sensor AS608) |

**Response 201:**
```json
{
  "data": {
    "id": 1,
    "finger_id": "100",
    "iot_device_id": 2,
    "device_nama": "ESP32 Fingerprint",
    "created_at": "2026-09-30T07:00:00.000000Z"
  }
}
```
**Response 422 (Duplikat):** `{ "message": "Finger ID sudah digunakan di device ini." }`
**Response 422 (Validasi):** `{ "message": "...", "errors": { "device_id": ["Device tidak valid."] } }`

> **Catatan:** `finger_id` harus unik per device. Satu device tidak boleh memiliki dua record dengan `finger_id` yang sama.

---

### `DELETE /api/employees/{id}/fingerprints/{fingerprintId}`
Hapus data fingerprint dari database.
| Auth | Bearer token |

**Response 200:** `{ "message": "Data fingerprint berhasil dihapus dari sistem." }`
**Response 404:** `{ "message": "Fingerprint tidak ditemukan untuk karyawan ini." }`

> ⚠️ Penghapusan dari modul sensor AS608 di ESP32 **harus dilakukan secara fisik/firmware terpisah** — endpoint ini hanya menghapus dari database.

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

### `GET /api/contracts/groups`
Daftar grup kontrak unik untuk frontend.
| Auth | Bearer token |
**Response 200:**
```json
[
  {
    "kontrak_mulai": "2026-07-01",
    "kontrak_selesai": "2027-03-31",
    "jumlah_karyawan": 18,
    "sudah_punya_periode": true,
    "nama_grup": "Kontrak 01 Jul 2026 – 31 Mar 2027"
  }
]
```

---

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

> **Catatan Pagination:** Semua endpoint yang mengembalikan paginate menggunakan **flat pagination Laravel** (bukan wrapper `meta`). Key yang tersedia: `current_page`, `data`, `first_page_url`, `from`, `last_page`, `last_page_url`, `links`, `next_page_url`, `path`, `per_page`, `prev_page_url`, `to`, `total`.
>
> **Catatan Tipe Data:** Field `C1`, `C2`, `C3`, `nilai_akhir` pada `/results` dikembalikan sebagai **string desimal** (misal: `"100.00"`), bukan float. Parse dengan `parseFloat()` di frontend.

### `GET /api/evaluation-periods`
| Auth | Bearer token |
| Query | `kontrak_mulai`, `kontrak_selesai`, `per_page` |

**Response 200:** Flat paginate. **Tidak menyertakan `spk_evaluations`** — detail ada di `/results` dan `/members`.
```json
{
  "data": [
    {
      "id": 1,
      "kontrak_mulai": "2026-07-01", "kontrak_selesai": "2027-03-31",
      "tanggal_mulai": "2026-09-01", "tanggal_selesai": "2026-09-30",
      "status": "selesai", "status_evaluasi": "sudah_dihitung",
      "nama": "Kontrak 01 Jul 2026 – 31 Mar 2027",
      "jumlah_anggota": 18,
      "jumlah_terhitung": 18
    }
  ],
  "current_page": 1,
  "last_page": 1,
  "per_page": 15,
  "total": 1
}
```

---

### `POST /api/evaluation-periods`
| Body | `kontrak_mulai`*, `kontrak_selesai`*, `tanggal_mulai`*, `tanggal_selesai`* |
**Response 201:** Periode evaluasi baru. Status otomatis `berjalan`.
**Response 422:** Harus dalam rentang kontrak, atau tumpang tindih dengan periode lain pada grup kontrak yang sama.

---

### `GET /api/evaluation-periods/{id}`
**Response 200:** Detail periode grup kontrak.

---

### `PUT /api/evaluation-periods/{id}`
| Query | `force=true` untuk menimpa periode yang sudah dihitung |
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
    "jumlah_karyawan": 18,
    "distribusi_kategori": { "positif": 5, "pertimbangan": 6, "perhatian": 4, "evaluasi_khusus": 3 },
    "warnings": ["Karyawan ID 35 memiliki 2 hari ABSENT tanpa keterangan"]
  }
}
```

---

### `GET /api/evaluation-periods/{id}/members`
Daftar anggota grup kontrak beserta status evaluasi masing-masing.
Anggota ditentukan dari kontrak yang `tanggal_mulai` dan `tanggal_selesai`-nya sama dengan `kontrak_mulai` / `kontrak_selesai` periode.
| Auth | Bearer token |

**Response 200:**
```json
{
  "evaluation_period_id": 22,
  "kontrak_mulai": "2026-07-01",
  "kontrak_selesai": "2027-03-31",
  "total": 18,
  "data": [
    {
      "employee_id": 25,
      "contract_id": 23,
      "nip": "EMP001",
      "nama": "Budi Santoso",
      "departemen": "IT",
      "jabatan": "Backend Developer",
      "sudah_dihitung": true,
      "nilai_akhir": "100.00",
      "kategori": "positif",
      "spk_evaluation_id": 47
    }
  ]
}
```
> `sudah_dihitung: false` → `nilai_akhir`, `kategori`, `spk_evaluation_id` = `null`.

---

### `GET /api/evaluation-periods/{id}/results`
Daftar hasil evaluasi periode (paginate, urut nilai_akhir DESC).
| Query | `kategori` (positif/pertimbangan/perhatian/evaluasi_khusus), `search` (nama karyawan), `per_page` |

**Response 200:** Flat paginate.
```json
{
  "data": [
    {
      "id": 47, "employee_id": 25, "nama": "Budi Santoso",
      "departemen": "IT",
      "C1": "100.00", "C2": "100.00", "C3": "100.00",
      "nilai_akhir": "100.00",
      "kategori": "positif",
      "calculated_at": "2026-10-08T19:11:44.000000Z"
    }
  ]
}
```
> `C1`, `C2`, `C3`, `nilai_akhir` adalah **string desimal**. Gunakan `parseFloat()` saat rendering.

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
Detail transparansi evaluasi lengkap. Menyertakan field lama (`details[]`, `keputusan_hrd`, `rekomendasi`) **dan** field baru (`langkah_saw`, `data_mentah`, `bukti_absensi`).

**Response 200:**
```json
{
  "id": 57,
  "employee": { "id": 35, "nama": "Agus Setiawan", "nip": "EMP011", "departemen": "Produksi" },
  "nilai_akhir": "79.00",
  "kategori": "perhatian",
  "rekomendasi": "Terdapat beberapa indikator kehadiran/ketepatan waktu yang kurang memuaskan. Direkomendasikan untuk pembinaan.",
  "calculated_at": "2026-10-08T19:11:44.000000Z",

  "details": [
    {
      "kode": "C1", "nama": "Kehadiran",
      "data_mentah": {
        "absent_tanpa_keterangan": 0, "absent_sah": 0,
        "daftar_tanggal_pemicu_penalti": []
      },
      "nilai_mentah": "100.00", "rumus_substitusi": "MAX(0, 100 - 0 * 60)",
      "nilai_normalisasi": "1.0000", "bobot": "0.5000", "kontribusi": "0.5000",
      "daftar_pemicu_penalti": []
    }
  ],

  "langkah_saw": {
    "langkah": [
      {
        "kode": "C1", "nama": "Kehadiran",
        "nilai_mentah": 100.0,
        "rumus_substitusi": "MAX(0, 100 - 0 * 60)",
        "normalisasi": 1.0,
        "bobot": 0.5,
        "kontribusi": 0.5
      },
      {
        "kode": "C2", "nama": "Ketepatan Waktu",
        "nilai_mentah": 40.0,
        "rumus_substitusi": "MAX(0, 100 - (6 * 10 + 0 * 40))",
        "normalisasi": 0.4,
        "bobot": 0.35,
        "kontribusi": 0.14
      },
      {
        "kode": "C3", "nama": "Kepatuhan ID Card",
        "nilai_mentah": 100.0,
        "rumus_substitusi": "(21 - 0) / 21 * 100",
        "normalisasi": 1.0,
        "bobot": 0.15,
        "kontribusi": 0.15
      }
    ],
    "total_kontribusi": 0.79,
    "nilai_akhir_persen": 79.0,
    "kategori": "perhatian"
  },

  "data_mentah": {
    "absent_tanpa_keterangan": 0,
    "absent_sah": 0,
    "telat_1_10": 6,
    "telat_lebih_10": 0,
    "total_hadir": 21,
    "pakai_fingerprint": 0
  },

  "bukti_absensi": [
    {
      "tanggal": "2026-09-01",
      "hari": "Selasa",
      "jam_masuk": "07:07:00",
      "metode": "RFID",
      "status": "TERLAMBAT",
      "menit_terlambat": 7,
      "jenis": "TELAT_1_10",
      "kriteria": "C2",
      "poin_penalti": 10
    }
  ],

  "keputusan_hrd": null
}
```

#### Field `langkah_saw`
| Field | Tipe | Keterangan |
|-------|------|------------|
| `langkah[].kode` | string | C1 / C2 / C3 |
| `langkah[].nama` | string | Nama kriteria |
| `langkah[].nilai_mentah` | float | Nilai sebelum normalisasi (0–100) |
| `langkah[].rumus_substitusi` | string | Rumus dengan angka asli yang tersubstitusi |
| `langkah[].normalisasi` | float | nilai_mentah / 100 |
| `langkah[].bobot` | float | Bobot desimal (0.5 / 0.35 / 0.15) |
| `langkah[].kontribusi` | float | normalisasi × bobot |
| `langkah[].dibatasi` | boolean (opsional) | `true` jika nilai_mentah dibatasi MAX(0,...) karena poin melebihi 100 |
| `total_kontribusi` | float | Jumlah semua kontribusi |
| `nilai_akhir_persen` | float | total_kontribusi × 100, dibulatkan 2 desimal |
| `kategori` | string | Kategori final |

#### Field `data_mentah`
| Field | Tipe | Keterangan |
|-------|------|------------|
| `absent_tanpa_keterangan` | int | Hari absen tanpa keterangan sah (mempengaruhi C1) |
| `absent_sah` | int | Hari absen dengan alasan sah (tidak mempengaruhi C1) |
| `telat_1_10` | int | Hari terlambat 1–10 menit (C2 penalti 10/hari) |
| `telat_lebih_10` | int | Hari terlambat >10 menit (C2 penalti 40/hari) |
| `total_hadir` | int | Hari hadir (HADIR + TERLAMBAT) dalam jendela evaluasi |
| `pakai_fingerprint` | int | Hari hadir dengan metode FINGERPRINT (C3 penalti) |

#### Field `bukti_absensi` — satu objek per kejadian penalti
| Field | Tipe | Keterangan |
|-------|------|------------|
| `tanggal` | string (Y-m-d) | Tanggal kejadian |
| `hari` | string | Nama hari dalam Bahasa Indonesia |
| `jam_masuk` | string \| null | Jam masuk (null untuk ABSENT) |
| `metode` | string \| null | RFID / FINGERPRINT / null |
| `status` | string | HADIR / TERLAMBAT / ABSENT |
| `menit_terlambat` | int | Menit keterlambatan (0 jika tidak terlambat) |
| `jenis` | string | `TELAT_1_10` / `TELAT_LEBIH_10` / `ABSEN_TANPA_KETERANGAN` / `ABSEN_SAH` / `FINGERPRINT` |
| `kriteria` | string | `C1` / `C2` / `C3` |
| `poin_penalti` | float | Poin penalti: TELAT_1_10=10, TELAT_LEBIH_10=40, ABSEN_TANPA_KETERANGAN=60, ABSEN_SAH=0, FINGERPRINT=100/total_hadir |

> **Konsistensi:** Untuk C1 dan C2 (jika tidak dibatasi): `100 - Σpoin_penalti = nilai_mentah`. Jika poin melebihi 100, nilai_mentah dibatasi ke 0 dan `langkah_saw.langkah[].dibatasi = true`.
>
> **total_hadir = 0:** C3 = 0, tidak ada baris `FINGERPRINT` di `bukti_absensi`. `data_mentah.total_hadir = 0`.
>
> **Satu hari bisa dua objek:** Jika seorang karyawan hadir via FINGERPRINT sekaligus terlambat, akan ada dua baris (satu `TELAT_*` untuk C2, satu `FINGERPRINT` untuk C3).

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

### `POST /api/devices/heartbeat`
Ping dari ESP32 untuk menandakan bahwa perangkat sedang aktif (online). Akan memperbarui `last_seen_at`.
| Header | `X-API-KEY: {plain_api_key}` |
**Response 200:** `{ "status": "ok" }`

### `POST /api/attendance/tap`
Kirim data tap absensi karyawan dari ESP32.
| Header | `X-API-KEY: {plain_api_key}` |
| Body | `device_id` (opsional/diabaikan), `metode`* (RFID/FINGERPRINT), `identifier`*, `timestamp`* (YYYY-MM-DDTHH:mm:ss) |

**Response 200 (Sukses):** `{ "status": "ok", "nama": "Budi Santoso" }` atau `{ "status": "ok", "nama": "Budi Santoso", "info": "sudah_absen" }`
**Response 404 (Tidak Ditemukan):** `{ "status": "not_found" }`
**Response 401 (API Key Salah):** `{ "message": "API key device tidak ditemukan." }`

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
| Periode evaluasi dalam batas grup kontrak | ✅ | `EvaluationPeriodController::store/update` |
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

---

## Catatan Integritas Database

### Perubahan FK — 2026-10-14 (migration `2026_10_14_000001`)

Migration `2026_10_14_000001_restrict_fk_employee_id_on_history_tables` mengubah perilaku foreign key pada tiga tabel riwayat:

| Tabel | Kolom | Sebelum | Sesudah |
|-------|-------|---------|---------|
| `attendance` | `employee_id` | `CASCADE DELETE` | `RESTRICT DELETE` |
| `contracts` | `employee_id` | `CASCADE DELETE` | `RESTRICT DELETE` |
| `spk_evaluations` | `employee_id` | `SET NULL` + nullable | `RESTRICT DELETE` + `NOT NULL` |

**Tabel tidak terpengaruh** (tetap `CASCADE DELETE`): `employee_cards`, `fingerprints`.

#### Implikasi untuk API

- `DELETE /api/employees/{id}` sudah diproteksi di level controller (422 jika ada riwayat) — lihat bagian [Employees](#employees). Database kini menambahkan lapisan kedua: jika proteksi controller terlewat, MySQL sendiri akan menolak DELETE dengan `SQLSTATE[23000] 1451`.
- `spk_evaluations.employee_id` tidak lagi bisa `NULL`. Kalkulasi SAW (`POST /api/evaluation-periods/{id}/calculate`) harus selalu menyertakan `employee_id` yang valid.
- `down()` migration mengembalikan ke kondisi semula (CASCADE + nullable) jika perlu rollback.

#### Kondisi Setelah Migrasi (diverifikasi 2026-10-14)
```
employees      : 25  ✓
spk_evaluations: 21  ✓ (0 baris NULL)
hrd_decisions  : 1   ✓
attendance     : 392 ✓
contracts      : 21  ✓
```
