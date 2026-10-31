# API Assumptions & Endpoint Documentation

Dokumen ini mencatat semua asumsi endpoint dan keputusan desain API yang tidak secara eksplisit diminta di spec awal.
Diperbarui setiap kali ada endpoint baru atau perubahan kontrak respons.

---

## POST /api/evaluations/{id}/recalculate

**Ditambahkan:** 2026-10-14
**Auth:** Bearer token (auth:sanctum)

### Deskripsi
Menghitung ulang SPK untuk SATU evaluasi karyawan berdasarkan `id` di tabel `spk_evaluations`.
Hanya karyawan tersebut yang dihitung ulang; evaluasi karyawan lain di periode yang sama tidak tersentuh.

### Invariant yang dijaga
| Invariant | Keterangan |
|-----------|------------|
| `spk_evaluations.id` tidak berubah | Pakai `updateOrCreate` pada `(evaluation_period_id, employee_id)` |
| `hrd_decisions` tidak diubah | Service tidak menyentuh tabel `hrd_decisions` sama sekali |
| `calculated_at` evaluasi lain tidak berubah | Hanya satu employee yang diproses |

### Response
Response identik dengan `GET /api/evaluations/{id}`, ditambah objek `sebelum`:

```json
{
  "id": 5,
  "employee": { ... },
  "nilai_akhir": "100.00",
  "kategori": "positif",
  "rekomendasi": "...",
  "calculated_at": "...",
  "details": [ ... ],
  "langkah_saw": { ... },
  "data_mentah": { ... },
  "bukti_absensi": [ ... ],
  "keputusan_hrd": null,
  "sebelum": {
    "nilai_akhir": "70.00",
    "kategori": "perhatian",
    "calculated_at": "2026-10-13T10:00:00.000000Z"
  }
}
```

### Kode Status
| Status | Kondisi |
|--------|---------|
| 200 | Berhasil dihitung ulang |
| 401 | Tidak ada / token tidak valid |
| 404 | `id` evaluasi tidak ditemukan |

### Asumsi
- **Contract diambil dari `evaluation->contract_id`** (bukan dicari ulang dari tabel contracts). Ini konsisten karena saat `calculateGroup` pertama kali dijalankan, `contract_id` sudah disimpan di baris evaluasi.
- **Jendela absensi** diambil dari `evaluationPeriod->tanggal_mulai` dan `evaluationPeriod->tanggal_selesai` (sama dengan saat hitung grup).
- **Bobot** selalu diambil dari `spk_criterias` terkini (bukan dari `bobot_snapshot` lama). Ini sesuai dengan perilaku `calculateGroup`.
- **`sebelum.nilai_akhir`** adalah nilai decimal cast dari model sebelum transaksi (format string `"70.00"` karena model cast `decimal:2`).

---

## GET /api/evaluations/{id}

Mengembalikan detail evaluasi satu karyawan termasuk:
- `data_mentah`: ringkasan angka kehadiran
- `langkah_saw`: langkah-langkah perhitungan SAW
- `bukti_absensi`: daftar kejadian penalti per hari
- `keputusan_hrd`: keputusan HRD jika ada

## PUT /api/evaluations/{id}/decision

Menyimpan atau memperbarui keputusan HRD untuk satu evaluasi.

**Request body:**
```json
{
  "decision": "DIPERPANJANG",
  "catatan": "Opsional"
}
```

---

## POST /api/evaluation-periods/{id}/calculate

Menghitung SPK untuk **semua karyawan** dalam satu periode evaluasi.
Karyawan ditentukan dari tabel `contracts` yang `tanggal_mulai` dan `tanggal_selesai`-nya cocok dengan `kontrak_mulai` / `kontrak_selesai` periode.

---

## Catatan Umum

- Endpoint `/api/attendance/tap` **KHUSUS ESP32** (middleware `device.auth`). Tidak boleh dipanggil dari frontend web.
- Semua endpoint yang memerlukan auth menggunakan Laravel Sanctum Bearer token.
- Format tanggal di response: ISO 8601 UTC (`Y-m-d\TH:i:s.u\Z`). Frontend mengkonversi ke WIB (GMT+7).
