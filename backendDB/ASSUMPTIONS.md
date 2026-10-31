# ASSUMPTIONS.md
# Asumsi Endpoint & Keputusan Desain Backend

> File ini mencatat setiap asumsi yang dibuat saat membangun skema database dan backend.
> Diperbarui setiap kali ada keputusan desain yang tidak eksplisit di requirements.

---

## Skema Database

### `employees`
- **Asumsi A1**: Kolom `posisi` di-rename menjadi `jabatan` karena requirements menyebut `jabatan`. Kolom `status` (enum `aktif/nonaktif`) dipertahankan berdampingan dengan `status_aktif` (boolean) — kolom lama tidak dihapus agar tidak merusak data. Ke depannya, `status_aktif` yang menjadi source of truth.
- **Asumsi A2**: `departemen` dibuat nullable karena karyawan bisa didaftarkan sebelum ditentukan departemennya.

### `iot_devices`
- **Asumsi A3**: `api_key_hash` menggunakan `sha256(plain_key)` — bukan bcrypt — agar bisa dilakukan lookup langsung saat device tap (hash deterministik). Bcrypt tidak cocok untuk autentikasi API key karena tidak bisa di-re-hash tanpa brute-force.
- **Asumsi A4**: `api_key_prefix` menyimpan 8 karakter pertama plaintext key (misal: `sk_ab12cd`) untuk keperluan identifikasi di UI HRD. Prefix ini bukan rahasia.
- **Asumsi A5**: `last_seen_at` (bukan `last_seen`) dipilih untuk konsistensi dengan konvensi Laravel (`created_at`, `updated_at`, `deleted_at`).

### `attendance`
- **Asumsi A6**: `is_auto_generated = true` diset oleh scheduler Laravel (Artisan Command) yang berjalan setiap malam untuk membuat record ABSENT bagi karyawan yang tidak tap sama sekali pada hari kerja.
- **Asumsi A7**: Index `(tanggal, status)` ditambah karena query laporan sering memfilter per tanggal dan status (misal: daftar ABSENT hari ini).
- **Asumsi A7b**: ABSENT tanpa `absence_reason_id` (atau alasan tidak sah) diperlakukan sebagai TIDAK SAH pada perhitungan C1. Ini ekuivalen dengan "Tanpa Keterangan".

### `evaluation_periods`
- **Asumsi A8a**: Jangka waktu (tanggal_mulai dan tanggal_selesai) harus berada di dalam batas waktu `contracts`.
- **Asumsi A8b**: Update atau Hapus pada `evaluation_periods` akan ditolak jika periode ini sudah dihitung (sudah memiliki `spk_evaluations`), kecuali menyertakan parameter `?force=true` untuk memaksanya.

### `spk_evaluations`
- **Asumsi A8**: `employee_id` dibuat nullable di database (foreign key), tetapi secara bisnis wajib diisi. Ini untuk kompatibilitas migration `fresh` — jika ada data lama sebelum migrasi, tidak error.
- **Asumsi A9**: `bobot_snapshot` format JSON: `{"C1": 0.50, "C2": 0.35, "C3": 0.15}`. Key menggunakan kode kriteria agar mudah di-lookup.
- **Asumsi A10**: Jika total HADIR/TERLAMBAT adalah 0 pada sebuah periode, maka kalkulasi C3 (Kepatuhan ID Card) akan disetel otomatis menjadi 0 untuk mencegah pembagian dengan 0 (`DivisionByZeroError`). Catatan khusus "Hadir 0, C3 otomatis 0" akan disimpan pada JSON `jumlah_data_mentah`.
- **Asumsi A10**: `calculated_at` vs `dihitung_pada` — dipilih `calculated_at` untuk konsistensi dengan nama kolom berbahasa Inggris di tabel lain (`created_at`, `updated_at`). Frontend menampilkan dalam Bahasa Indonesia.
- **Asumsi A11**: Constraint `UNIQUE(evaluation_period_id, employee_id)` memastikan satu karyawan hanya dihitung sekali per periode. Jika perlu re-kalkulasi, record lama harus dihapus dulu.

### `spk_evaluation_details`
- **Asumsi A12**: `nilai_terbobot` di-rename menjadi `kontribusi` karena semantiknya lebih tepat: kontribusi setiap kriteria terhadap nilai akhir SAW = `nilai_normalisasi * bobot`.
- **Asumsi A13**: Format `jumlah_data_mentah` berbeda per kriteria:
  - C1: `{"total_hari_kerja": 22, "absent_tanpa_keterangan": 0}`
  - C2: `{"telat_1_10_menit": 1, "telat_lebih_10_menit": 0}`
  - C3: `{"total_hadir": 22, "pakai_fingerprint": 2}`

### `hrd_decisions`
- **Asumsi A14**: Kolom `decision` menggunakan enum `DIPERPANJANG/TIDAK_DIPERPANJANG/DITUNDA` sesuai permintaan. Tidak ada label "Diterima/Ditolak" sesuai rules.
- **Asumsi A15**: `decided_by` adalah FK ke tabel `users` (bukan ke `employees`). HRD adalah user sistem, bukan karyawan yang dievaluasi.
- **Asumsi A16**: `cascadeOnDelete` pada `spk_evaluation_id` — jika evaluasi dihapus, keputusan HRD ikut terhapus. `restrictOnDelete` pada `decided_by` — tidak bisa hapus user HRD jika masih punya keputusan terdaftar.

### `holidays`
- **Asumsi A17**: Tabel `holidays` hanya menyimpan tanggal libur nasional/cuti bersama. Hari Sabtu-Minggu sudah dikecualikan via setting `work_days`, tidak perlu didaftarkan ke tabel ini.

### `settings`
- **Asumsi A18**: Setting `work_days` menyimpan JSON array angka ISO day-of-week (1=Senin...7=Minggu). Default: `[1,2,3,4,5]` (Senin-Jumat).
- **Asumsi A19**: Setting `jam_masuk` menyimpan string format `HH:MM` (misal: `"07:00"`). Parsing dilakukan di backend saat menghitung status kehadiran.

---

## Seeder

- **Asumsi A20**: User HRD default: email `hrd@spk-absensi.local`, password `password`. **WAJIB diganti** di production via Artisan tinker atau fitur ubah password.
- **Asumsi A21**: `SpkCriteriaSeeder` menyimpan bobot sebagai `50.00`, `35.00`, `15.00` (skala 0–100). Backend harus membagi 100 saat normalisasi (`bobot / 100 = 0.50`).

---

## Endpoint ESP32 (TIDAK digunakan di frontend)

- `/api/attendance/tap` — khusus ESP32. Menerima `{rfid_uid atau fingerprint_id, device_api_key_prefix+hash}`. Frontend TIDAK boleh memanggil endpoint ini.
- Autentikasi device: header `X-Device-Key: {prefix}.{sha256_hash}` — backend split by `.`, cari device by prefix, lalu verify hash.

---

## Dashboard

- **Asumsi A22**: `GET /api/dashboard` menggunakan **tanggal terakhir yang memiliki data attendance** (bukan selalu hari ini) sebagai referensi kartu kehadiran. Jika tidak ada data sama sekali, semua angka kartu dikembalikan 0.
- **Asumsi A23**: `absen_fingerprint` pada kartu dashboard dihitung hanya untuk hari referensi, bukan seluruh periode. Ini menunjukkan berapa banyak orang yang tap fingerprint hari ini.
- **Asumsi A24**: Tanpa `period_id`, `rekap_kehadiran` merangkum 30 hari terakhir. Dengan `period_id`, rentang disesuaikan dengan `tanggal_mulai` – `tanggal_selesai` periode tersebut.
- **Asumsi A25**: `rata_rata_kriteria` (C1, C2, C3) hanya tersedia jika `period_id` diisi DAN periode sudah memiliki hasil kalkulasi. Jika belum, nilai dikembalikan `null`.
- **Asumsi A26**: Keempat kategori evaluasi selalu dikembalikan di `distribusi_kategori` (termasuk yang bernilai 0), agar frontend tidak perlu menangani missing key.
- **Asumsi A27**: Device dianggap **aktif** jika `last_seen_at` ≤ 15 menit yang lalu. Threshold 15 menit ini sama antara `GET /api/dashboard` (peringatan) dan `GET /api/devices/status`. Jika perlu dikonfigurasi, dapat dipindah ke tabel `settings`.
- **Asumsi A28**: `GET /api/dashboard/attendance-trend?range=` hanya menerima nilai `7` atau `30`. Nilai lain di-default ke 7. Hari tanpa data tetap dikembalikan dengan nilai 0 agar grafik kontinu.
- **Asumsi A29**: Route `GET /api/devices/status` dideklarasikan **sebelum** `apiResource('devices')` di `routes/api.php` untuk mencegah segmen `/status` ditangkap sebagai parameter `{device}` oleh Laravel router.

---

## Hal yang Belum Diputuskan / Perlu Konfirmasi

| ID | Pertanyaan | Default Sementara |
|----|-----------|-------------------|
| Q1 | Apakah `contracts` dan `evaluation_periods` tetap dipisah atau digabung? | Tetap terpisah (ada di schema lama) |
| Q2 | Apakah perlu soft delete (`deleted_at`) untuk `employees`? | Tidak (gunakan `status_aktif = false`) |
| Q3 | Batas menit terlambat untuk kategori "telat >10 menit"? | >10 menit (lihat rules: `telat_>10_menit*40`) |
| Q4 | Apakah `hrd_decisions` perlu riwayat (audit log) jika keputusan diubah? | Tidak untuk saat ini, 1 keputusan per evaluasi |

---

## Export Laporan

- **Asumsi A30**: `GET /api/evaluation-periods/{id}/export?format=pdf` menghasilkan PDF landscape A4 via dompdf. Response adalah download stream (`Content-Disposition: attachment`), bukan base64 atau URL.
- **Asumsi A31**: `GET /api/evaluation-periods/{id}/export?format=xlsx` menghasilkan Excel (`.xlsx`) dengan 2 sheet: "Hasil" (ringkasan per karyawan) dan "Rincian" (nilai mentah, normalisasi, bobot, kontribusi per kriteria). Library: maatwebsite/excel 3.1.x (kompatibel PHP 8.1/8.2).
- **Asumsi A32**: Nama file export menggunakan format `laporan-evaluasi-{tanggal_mulai}-{tanggal_selesai}-{hari_ini}.{ext}` dengan tanggal dalam format `Ymd`. Contoh: `laporan-evaluasi-20260801-20260831-20260901.pdf`.
- **Asumsi A33**: `GET /api/evaluations/{id}/export` menghasilkan PDF rincian satu karyawan. Nama file: `rincian-evaluasi-{nama-slug}-{from}-{to}-{today}.pdf`.

---

## Kategori (Audit Fix)

- **Asumsi A34** *(BUG FIX)*: Slug kategori yang disimpan di database adalah `evaluasi_khusus` (snake_case), bukan `khusus`. Bug ditemukan saat audit — `SpkCalculationService` sebelumnya memetakan output `SawCalculator::categorize()` dengan salah: `'evaluasi khusus'` → `'khusus'`. Sudah diperbaiki di commit ini. Migrasi `2026_09_29_143036_alter_spk_evaluations_fix_schema.php` mengubah ENUM dari `['positif','pertimbangan','perhatian','khusus']` menjadi `['positif','pertimbangan','perhatian','evaluasi_khusus']`.
- **Asumsi A35**: Kolom `employee_id` dan `bobot_snapshot` serta `calculated_at` **tidak ada** di migration awal `create_spk_evaluations_table` — ditambahkan via migration fix. Ini karena schema awal ditulis sebelum implementasi `SpkCalculationService` yang memerlukan kolom tersebut.

---

## DemoSeeder

- **Asumsi A36**: `DemoSeeder` **terpisah** dari `DatabaseSeeder` — hanya dijalankan manual: `php artisan db:seed --class=DemoSeeder`. Tidak dieksekusi saat `php artisan migrate --seed` atau saat test. Ini memastikan test suite (yang menggunakan `RefreshDatabase`) tidak terpengaruh data demo.
- **Asumsi A37**: DemoSeeder menggunakan periode Agustus 2026 (1–31 Agustus). Kontrak karyawan dimulai Juli 2026. Libur nasional: 17 Agustus 2026 (HUT RI ke-81). Total hari kerja efektif: ±21 hari.
- **Asumsi A38**: 15 karyawan demo dibagi 5 departemen (IT, HRD, Finance, Marketing, Operasional), masing-masing 3 karyawan. Profil absensi dirancang menghasilkan keempat kategori SAW: 5 Positif, 4 Pertimbangan, 3 Perhatian, 3 Evaluasi Khusus.
- **Asumsi A39**: Tabel `holidays` menggunakan kolom `keterangan` (bukan `nama`) sesuai schema yang ada. Ini dicatat setelah ditemukan `Unknown column 'nama'` saat menjalankan DemoSeeder pertama kali.

---

## Audit N+1 & Logika Bisnis

- **Asumsi A40**: Hasil audit N+1: tidak ditemukan N+1 pada controller. Semua query menggunakan `with()` eager loading atau agregasi SQL langsung. Titik rawan yang sudah diamankan:
  - `DashboardController`: semua kartu pakai satu query agregasi SQL per metrik.
  - `AttendanceController::index`: eager load `['employee', 'absenceReason']` dalam satu query.
  - `EvaluationPeriodController::results`: eager load `['employee', 'details.spkCriteria']`.
  - `SpkCalculationService::calculate`: satu query `Attendance::with('absenceReason')` untuk semua baris periode.
- **Asumsi A41**: Tidak ada logika bisnis (kalkulasi SAW, penentuan kategori, penentuan status HADIR/TERLAMBAT) di controller. Controller hanya menerima input, memanggil service, dan mengembalikan response. Semua kalkulasi ada di `SawCalculator` dan `SpkCalculationService`.

