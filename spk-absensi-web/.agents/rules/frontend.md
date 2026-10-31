---
trigger: always_on
---

Rule ini berlaku untuk folder `frontend/` (React). Semua path relatif terhadap `frontend/`. Jangan mengubah file di `backend/` atau folder firmware.

# Konteks
Dashboard web untuk HRD pada SPK Evaluasi Kedisiplinan Karyawan Berbasis IoT (tugas kuliah). Data dari backend Laravel 12 (REST API). Hasil SPK hanyalah ALAT BANTU, keputusan akhir di HRD. Jangan gunakan kata "Diterima/Ditolak", gunakan label kategori saja.

# Tech Stack (wajib)
React + Vite + TypeScript, Tailwind CSS, shadcn/ui, Axios (instance terpusat + interceptor token), TanStack Query, React Router, React Hook Form + Zod, Recharts.
Auth: Laravel Sanctum (Bearer token). Redirect ke /login jika 401.

# Aturan Bisnis (KRITIS, jangan diubah)
- Jam masuk 07:00. Status HADIR/TERLAMBAT ditentukan dari jam masuk, bukan metode absen. TERLAMBAT tetap dihitung hadir.
- RFID: tanpa penalti. Fingerprint: penalti ringan di C3 saja. Label UI untuk fingerprint: "Absen Fingerprint" (bukan "Lupa ID Card").
- Alasan sah (Menikah, Keluarga Meninggal, Dirawat di RS) tidak dihitung pelanggaran. "Tanpa Keterangan" tidak sah.
- Bobot (semua benefit): C1 Kehadiran 50%, C2 Ketepatan Waktu 35%, C3 Kepatuhan ID Card 15%.
- Rumus (DIHITUNG DI BACKEND, frontend hanya menampilkan):
  C1 = MAX(0, 100 - absent_tanpa_keterangan*60)
  C2 = MAX(0, 100 - (telat_1_10_menit*10 + telat_>10_menit*40))
  C3 = (hadir - pakai_fingerprint) / hadir * 100
  Normalisasi = nilai_mentah / 100
  Nilai Akhir = C1n*0.50 + C2n*0.35 + C3n*0.15 (tampil dalam persen)
- Ada 4 kategori: 90-100 Positif (hijau) | 80-89 Perlu Pertimbangan (kuning) | 70-79 Perlu Perhatian (oranye) | <70 Evaluasi Khusus (merah).
- Periode evaluasi eksplisit (tanggal mulai/akhir) milik kontrak, bukan bulan kalender, tidak dihardcode.
- Setiap nilai akhir harus punya halaman detail transparansi (mentah, rumus + substitusi angka, normalisasi, bobot, kontribusi).

# Aturan Kode
- TypeScript strict, dilarang memakai `any`.
- Komponen kecil dan reusable. Tidak ada logika SAW di frontend (termasuk menghitung kategori dari nilai; pakai yang dikirim backend).
- Semua pemanggilan API lewat `src/api/`. Endpoint `/api/attendance/tap` khusus ESP32, JANGAN dipakai di frontend.
- Mode mock via `VITE_USE_MOCK=true`; data mock harus konsisten secara matematis dengan rumus di atas.
- Setiap halaman wajib punya loading (skeleton), empty, dan error state.
- Bahasa UI: Bahasa Indonesia. Format tanggal dd MMM yyyy, zona WIB.
- Badge kategori harus menyertakan teks, bukan hanya warna.
- Dialog konfirmasi sebelum aksi hapus.
- Jangan menambah fitur di luar yang diminta. Catat asumsi endpoint di API_ASSUMPTIONS.md.

# Mockup dashboard
Hanya referensi LAYOUT dan gaya visual. Bila angka/label mockup bertentangan dengan rules ini, rules yang menang. Hapus dari mockup: ikon lonceng notifikasi dan menu Pengaturan. Menu "Kriteria" hanya tampilan read-only.

# Di luar scope
Multi-tenant, WebSocket/notifikasi real-time, mobile app.