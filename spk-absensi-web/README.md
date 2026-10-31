# SPK Absensi Web (Frontend)

Sistem Pendukung Keputusan (SPK) untuk rekomendasi perpanjangan kontrak karyawan berdasarkan data absensi menggunakan metode SAW (Simple Additive Weighting).

## Tech Stack
- React 18
- Vite
- TypeScript
- Tailwind CSS
- React Router DOM
- TanStack Query (React Query)
- Axios
- React Hook Form + Zod
- Recharts
- MSW (Mock Service Worker)

## Fitur & Modul
- **Dashboard**: Ringkasan data karyawan dan grafik absensi.
- **Karyawan**: CRUD Karyawan, pendaftaran RFID, dan histori Fingerprint.
- **Perangkat IoT**: Manajemen mesin absensi (Fingerprint/RFID) dengan otentikasi API Key.
- **Absensi**: Rekapitulasi absensi harian (read-only), HRD dapat menyesuaikan alasan ketidakhadiran (Sakit/Izin/Cuti/Alpha).
- **Kontrak & Periode**: Penjadwalan tanggal evaluasi SPK berdasarkan durasi kontrak.
- **Evaluasi SPK**: Eksekusi algoritma SAW dengan fitur transparansi langkah perhitungan, rekomendasi (Perpanjang, Pertimbangkan, Putus Kontrak).
- **Laporan**: Unduh laporan evaluasi dalam format PDF, Excel, dan siap cetak.

## Prasyarat
- Node.js versi 18 atau lebih baru.
- Backend Laravel berjalan di port 8000 (jika menggunakan Real API).

## Setup & Instalasi
1. Masuk ke folder proyek: `cd spk-absensi-web`
2. Install dependensi: `npm install`
3. Salin file environment: `cp .env.example .env.local`
4. Sesuaikan variabel environment di `.env.local`:
   - `VITE_API_URL=http://localhost:8000/api` (URL Backend Laravel)
   - `VITE_USE_MOCK=true` (Gunakan Mock/MSW untuk simulasi API tanpa backend)

## Mode Mock (Simulasi Tanpa Backend)
Jika backend belum siap, Anda dapat menjalankan aplikasi menggunakan Mock Data.
1. Pastikan `VITE_USE_MOCK=true` di `.env.local`
2. Jalankan aplikasi: `npm run dev`
3. Login menggunakan sembarang kredensial (misal: email `admin@example.com`, password bebas).
4. MSW akan mencegat semua permintaan API ke `VITE_API_URL` dan merespons dengan data dummy yang konsisten (terdapat di `src/mocks/data.ts`).

Untuk beralih ke Real API, ubah `VITE_USE_MOCK=false`.

## Struktur Utama
- `src/api/` -> Definisi request API (Axios wrapper).
- `src/components/` -> Komponen reusable (Layout, Sidebar, Toast).
- `src/pages/` -> Halaman utama aplikasi (Dashboard, Karyawan, dll).
- `src/types/` -> Definisi antarmuka (interface) TypeScript.
- `src/mocks/` -> Logika pencegatan dan pembuatan data tiruan MSW.

## Membangun untuk Produksi
```bash
npm run build
npm run preview
```
