---
trigger: always_on
---

# Konteks Project
SPK Evaluasi Kedisiplinan Karyawan Berbasis IoT (tugas kuliah). Karyawan absen lewat ESP32 (RFID + fingerprint) -> backend Laravel 12 + MySQL -> dihitung dengan metode SAW -> ditampilkan di dashboard web untuk HRD.
Hasil SPK hanyalah ALAT BANTU. Jangan gunakan kata "Diterima/Ditolak". Gunakan label kategori saja.

# Tech Stack (wajib)
React + Vite + TypeScript, Tailwind CSS, shadcn/ui, Axios (instance terpusat + interceptor token), TanStack Query, React Router, React Hook Form + Zod, Recharts.
Auth: Laravel Sanctum (Bearer token). Redirect ke /login jika 401.

# Aturan Bisnis (KRITIS, jangan diubah)
- Jam masuk 07:00. Status HADIR/TERLAMBAT ditentukan dari jam masuk, bukan metode absen.
- RFID: tanpa penalti. Fingerprint: penalti ringan di C3 saja.
- Alasan sah (Menikah, Keluarga Meninggal, Dirawat di RS) tidak dihitung pelanggaran. "Tanpa Keterangan" tidak sah.
- Bobot (semua benefit): C1 Kehadiran 50%, C2 Ketepatan Waktu 35%, C3 Kepatuhan ID Card 15%.
- Rumus (DIHITUNG DI BACKEND, frontend hanya menampilkan):
  C1 = MAX(0, 100 - absent_tanpa_keterangan*60)
  C2 = MAX(0, 100 - (telat_1_10_menit*10 + telat_>10_menit*40))
  C3 = (hadir - pakai_fingerprint) / hadir * 100
  Normalisasi = nilai_mentah / 100
  Nilai Akhir = C1n*0.50 + C2n*0.35 + C3n*0.15 (tampil dalam persen)
- Kategori: 90-100 Positif (hijau) | 80-89 Perlu Pertimbangan (kuning) | 70-79 Perlu Perhatian (oranye) | <70 Evaluasi Khusus (merah)
- Periode evaluasi disimpan eksplisit (tanggal mulai/akhir), TIDAK dihardcode.

# Aturan Kode
- TypeScript strict, dilarang memakai `any`.
- Komponen kecil dan reusable. Tidak ada logika SAW di frontend.
- Semua pemanggilan API lewat folder `src/api/`. Endpoint `/api/attendance/tap` khusus ESP32, JANGAN dipakai di frontend.
- Mode mock via `VITE_USE_MOCK=true`.
- Setiap halaman wajib punya loading (skeleton), empty, dan error state.
- Bahasa UI: Bahasa Indonesia. Format tanggal dd MMM yyyy, zona WIB.
- Badge kategori harus menyertakan teks, bukan hanya warna.
- Dialog konfirmasi sebelum aksi hapus.
- Jangan menambah fitur di luar yang diminta. Setiap asumsi endpoint dicatat di API_ASSUMPTIONS.md.

# Di luar scope
Multi-tenant, WebSocket/notifikasi real-time, mobile app.
- Ada 4 kategori hasil (bukan 3): Positif, Perlu Pertimbangan, Perlu Perhatian, Evaluasi Khusus.
- Mockup dashboard hanyalah referensi layout; bila angka/label mockup bertentangan dengan rules ini, rules yang menang.



