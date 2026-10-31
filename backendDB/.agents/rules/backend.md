---
trigger: always_on
---

Rule ini berlaku untuk folder `backend/` (Laravel). Semua path relatif terhadap `backend/`. Jangan mengubah file di `frontend/` atau folder firmware.

# Konteks
Backend SPK Evaluasi Kedisiplinan Karyawan Berbasis IoT (tugas kuliah). ESP32 (RFID + fingerprint) mengirim data absen ke API -> Laravel 12 + MySQL -> dihitung dengan SAW -> dikonsumsi dashboard React untuk HRD.
Hasil SPK adalah ALAT BANTU, keputusan akhir ada di HRD. Jangan gunakan istilah "Diterima/Ditolak".

# Tech
Laravel 12, PHP 8.2+, MySQL, Sanctum (login HRD), API key untuk device IoT. Timezone aplikasi: Asia/Jakarta.

# Aturan Bisnis (KRITIS, jangan diubah tanpa konfirmasi)
- Jam masuk standar 07:00. Lewat = TERLAMBAT. Status ditentukan HANYA dari jam masuk, bukan metode.
- Tap pertama dalam sehari = jam masuk. Tap berikutnya di hari yang sama diabaikan.
- RFID: tanpa penalti. FINGERPRINT: penalti ringan di C3 saja, tidak otomatis telat/absen.
- absence_reasons punya flag is_sah. Sah: Menikah, Keluarga Meninggal, Dirawat di Rumah Sakit. Tidak sah: Tanpa Keterangan. Absen sah tidak dihitung pelanggaran. ABSENT tanpa alasan diperlakukan sebagai tidak sah.
- Bobot (semua benefit): C1 50%, C2 35%, C3 15%.
- C1 = MAX(0, 100 - absent_tanpa_keterangan*60)
- C2 = MAX(0, 100 - (telat_1_10_menit*10 + telat_lebih_10_menit*40))
- C3 = (hadir - pakai_fingerprint)/hadir*100, hadir = hari berstatus HADIR atau TERLAMBAT. Jika hadir = 0, C3 = 0 (keputusan desain, catat di ASSUMPTIONS.md).
- Normalisasi = nilai_mentah/100. Nilai Akhir = C1n*0.50 + C2n*0.35 + C3n*0.15, disimpan juga dalam persen (2 desimal).
- Kategori dari nilai akhir persen (tanpa pembulatan sebelum penentuan): >=90 Positif | >=80 Perlu Pertimbangan | >=70 Perlu Perhatian | <70 Evaluasi Khusus. Ada 4 kategori.
- Periode evaluasi disimpan eksplisit di evaluation_periods, tidak dihardcode.
- Transparansi: setiap nilai akhir harus bisa ditelusuri lewat spk_evaluation_details (jumlah mentah, nilai mentah, normalisasi, bobot, kontribusi).
- Bobot yang dipakai di-snapshot ke spk_evaluations.bobot_snapshot. Tidak ada endpoint untuk mengubah bobot.
- status dan menit_terlambat dihitung backend saat insert, bukan dikirim ESP32.
- ABSENT dibuat otomatis oleh scheduler untuk karyawan yang tidak tap, kecuali akhir pekan dan tanggal di tabel holidays. Harus idempotent.
- API key device disimpan sebagai hash, plaintext hanya tampil sekali saat dibuat/regenerate.

# Aturan Kode
- Logika bisnis di Service class, bukan di Controller. Controller tipis.
- Validasi lewat FormRequest. Response lewat API Resource. List: { data, meta } (paginate). Error validasi 422 standar Laravel, pesan Bahasa Indonesia.
- Perhitungan waktu memakai Carbon timezone Asia/Jakarta.
- Perubahan skema lewat migration BARU, jangan mengedit migration lama yang sudah dijalankan.
- Hindari N+1, gunakan agregasi SQL untuk dashboard.
- Setiap fitur wajib punya Feature/Unit test. Jangan menandai selesai jika `php artisan test` gagal.
- Endpoint ESP32 (/api/attendance/tap) pakai middleware API key. Endpoint HRD pakai auth:sanctum.
- Jangan menambah fitur di luar yang diminta. Catat asumsi di ASSUMPTIONS.md.

# Di luar scope
Multi-tenant, WebSocket/push notification, mobile app.