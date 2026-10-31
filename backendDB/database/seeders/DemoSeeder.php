<?php

namespace Database\Seeders;

use App\Models\AbsenceReason;
use App\Models\Attendance;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\EmployeeCard;
use App\Models\EvaluationPeriod;
use App\Models\Fingerprint;
use App\Models\Holiday;
use App\Models\IotDevice;
use App\Services\SpkCalculationService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * DemoSeeder — dipisah dari DatabaseSeeder (seeder wajib).
 *
 * PRASYARAT: Jalankan DatabaseSeeder terlebih dahulu.
 *   php artisan db:seed               ← (seeder wajib)
 *   php artisan db:seed --class=DemoSeeder
 *
 * Data yang dibuat:
 *  - 2 device IoT (RFID Reader + Fingerprint Scanner)
 *  - 18 karyawan (5 departemen, nama Indonesia realistis)
 *  - Kartu RFID (1 per karyawan) + Fingerprint (separuh karyawan)
 *  - Hari libur perusahaan September 2026
 *  - Kontrak aktif per karyawan (Juli 2026 – Maret 2027)
 *  - Periode evaluasi September 2026 per kontrak
 *  - Data absensi 21 hari kerja aktif di September 2026 dengan variasi
 *    yang menghasilkan keempat kategori SAW.
 *  - Kalkulasi SPK dijalankan otomatis setelah seeder selesai.
 *
 * RUMUS C2 YANG BENAR (diverifikasi dari SpkCalculationService):
 *   C2 = MAX(0, 100 - (telat_1_10*10 + telat_lebih10*40))
 *   Contoh: 1x>10mnt → C2 = 100-40 = 60 (BUKAN 60+40=100 atau apapun lain)
 *           2x>10mnt → C2 = 100-80 = 20
 *           3x>10mnt → C2 = MAX(0,100-120) = 0
 */
class DemoSeeder extends Seeder
{
    private const EVAL_FROM     = '2026-09-01';
    private const EVAL_TO       = '2026-09-30';
    private const CONTRACT_FROM = '2026-07-01';
    private const CONTRACT_TO   = '2027-03-31';

    public function run(): void
    {
        $this->command->info('🚀 Memulai DemoSeeder...');

        // ── 1. Devices IoT ────────────────────────────────────────────────────
        $this->command->info('  [1/6] Membuat device IoT...');
        $deviceRfid = $this->createDevice('RFID Reader - Pintu Utama',         'rfid_reader_lobby');
        $deviceFp   = $this->createDevice('Fingerprint Scanner - Pintu Utama',  'fp_scanner_lobby');

        // ── 2. Hari Libur ─────────────────────────────────────────────────────
        $this->command->info('  [2/6] Menambahkan hari libur...');
        Holiday::updateOrCreate(
            ['tanggal' => '2026-09-07'],
            ['tanggal' => '2026-09-07', 'keterangan' => 'Libur Perusahaan — Company Anniversary']
        );

        // ── 3. Hari kerja aktual ──────────────────────────────────────────────
        $workDays = $this->getWorkDays(self::EVAL_FROM, self::EVAL_TO);
        $this->command->info("  [3/6] Hari kerja aktif September 2026: " . count($workDays) . " hari");

        // ── 4. Absence Reasons ────────────────────────────────────────────────
        $reasonHospitalized = AbsenceReason::where('kode', 'HOSPITALIZED')->first();
        $reasonMarriage     = AbsenceReason::where('kode', 'MARRIAGE')->first();
        $reasonFamilyDeath  = AbsenceReason::where('kode', 'FAMILY_DEATH')->first();
        $reasonNoReason     = AbsenceReason::where('kode', 'NO_REASON')->first();

        // ── 5. Karyawan + Kartu + Fingerprint + Kontrak + Periode + Absensi ──
        $this->command->info('  [5/6] Membuat karyawan, kontrak, absensi...');
        $profiles = $this->employeeProfiles();
        $fingerprintCounter = 1;

        // Buat 1 periode evaluasi untuk grup kontrak ini
        $period = EvaluationPeriod::updateOrCreate(
            ['kontrak_mulai' => self::CONTRACT_FROM, 'kontrak_selesai' => self::CONTRACT_TO, 'tanggal_mulai' => self::EVAL_FROM, 'tanggal_selesai' => self::EVAL_TO],
            ['status' => 'selesai']
        );

        foreach ($profiles as $idx => $profile) {
            $nip = 'EMP' . str_pad($idx + 1, 3, '0', STR_PAD_LEFT);

            $emp = Employee::updateOrCreate(
                ['nip' => $nip],
                [
                    'nama'         => $profile['nama'],
                    'departemen'   => $profile['departemen'],
                    'jabatan'      => $profile['jabatan'],
                    'status_aktif' => true,
                    'status'       => 'aktif',
                ]
            );

            // Kartu RFID
            EmployeeCard::updateOrCreate(
                ['employee_id' => $emp->id],
                ['uid_rfid' => strtoupper(bin2hex(random_bytes(4))), 'status' => 'aktif']
            );

            // Fingerprint (separuh karyawan)
            if ($profile['fingerprint_days'] > 0) {
                Fingerprint::updateOrCreate(
                    ['iot_device_id' => $deviceFp->id, 'employee_id' => $emp->id],
                    ['finger_id' => $fingerprintCounter++]
                );
            }

            // Kontrak
            $contract = Contract::updateOrCreate(
                ['employee_id' => $emp->id, 'tanggal_mulai' => self::CONTRACT_FROM],
                ['tanggal_selesai' => self::CONTRACT_TO, 'status' => 'aktif']
            );

            // Absensi
            $this->generateAttendance(
                $emp, $workDays, $deviceRfid, $deviceFp, $profile,
                $reasonHospitalized, $reasonMarriage, $reasonFamilyDeath, $reasonNoReason
            );

            $this->command->line("      ✔ {$nip} {$profile['nama']} [{$profile['departemen']}]"
                . " target={$profile['target_kategori']}");
        }

        // ── 6. Hitung SPK ─────────────────────────────────────────────────────
        $this->command->info('  [6/6] Menghitung SPK untuk semua periode demo...');
        /** @var SpkCalculationService $svc */
        $svc = app(SpkCalculationService::class);

        $svc->calculateGroup($period);
        $period->refresh();
        
        $evals = $period->spkEvaluations;
        $distribusi = [];
        foreach ($evals as $eval) {
            $kat = $eval->kategori;
            $distribusi[$kat] = ($distribusi[$kat] ?? 0) + 1;
        }

        // ── Laporan ───────────────────────────────────────────────────────────
        $this->command->newLine();
        $this->command->info('✅ DemoSeeder selesai!');
        $this->command->info('📊 Distribusi Kategori SPK:');
        $labelMap = [
            'positif'         => 'Positif        (≥90)',
            'pertimbangan'    => 'Pertimbangan (80–89)',
            'perhatian'       => 'Perhatian    (70–79)',
            'evaluasi_khusus' => 'Evaluasi Khusus (<70)',
        ];
        foreach ($labelMap as $slug => $label) {
            $count = $distribusi[$slug] ?? 0;
            $bar   = str_repeat('█', $count);
            $this->command->line("   {$label}: {$count}  {$bar}");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    private function createDevice(string $nama, string $lokasi): IotDevice
    {
        $plain = 'sk_' . Str::random(32);
        return IotDevice::updateOrCreate(
            ['lokasi' => $lokasi],
            [
                'nama'           => $nama,
                'api_key_hash'   => hash('sha256', $plain),
                'api_key_prefix' => substr($plain, 0, 8),
                'last_seen_at'   => Carbon::now()->subMinutes(rand(1, 60)),
            ]
        );
    }

    private function getWorkDays(string $from, string $to): array
    {
        $holidays = Holiday::whereBetween('tanggal', [$from, $to])
            ->pluck('tanggal')
            ->map(fn($t) => (string) $t)
            ->toArray();

        $days    = [];
        $current = Carbon::parse($from);
        $end     = Carbon::parse($to);
        while ($current->lte($end)) {
            $dow = $current->dayOfWeek;
            if ($dow !== Carbon::SUNDAY && $dow !== Carbon::SATURDAY
                && !in_array($current->toDateString(), $holidays)
            ) {
                $days[] = $current->toDateString();
            }
            $current->addDay();
        }
        return $days;
    }

    private function generateAttendance(
        Employee       $emp,
        array          $workDays,
        IotDevice      $deviceRfid,
        IotDevice      $deviceFp,
        array          $profile,
        ?AbsenceReason $reasonHospitalized,
        ?AbsenceReason $reasonMarriage,
        ?AbsenceReason $reasonFamilyDeath,
        ?AbsenceReason $reasonNoReason
    ): void {
        // Idempotent: hapus attendance lama di periode ini
        Attendance::where('employee_id', $emp->id)
            ->whereBetween('tanggal', [self::EVAL_FROM, self::EVAL_TO])
            ->delete();

        $noReasonLeft    = $profile['absent_tanpa_alasan'];
        $hospLeft        = $profile['absent_hosp'];
        $marriageLeft    = $profile['absent_marriage'];
        $familyDeathLeft = $profile['absent_family_death'];
        $telat1_10Left   = $profile['telat_1_10'];
        $telatLbh10Left  = $profile['telat_lebih10'];
        $fpLeft          = $profile['fingerprint_days'];

        foreach ($workDays as $tanggal) {
            // ABSENT tanpa keterangan (auto_generated = simulasi scheduler)
            if ($noReasonLeft > 0) {
                Attendance::create([
                    'employee_id'       => $emp->id,
                    'iot_device_id'     => null,
                    'tanggal'           => $tanggal,
                    'jam_masuk'         => null,
                    'jam_keluar'        => null,
                    'metode'            => null,
                    'status'            => 'ABSENT',
                    'menit_terlambat'   => 0,
                    'absence_reason_id' => $reasonNoReason?->id,
                    'is_auto_generated' => true,
                ]);
                $noReasonLeft--;
                continue;
            }

            // ABSENT dirawat RS (sah)
            if ($hospLeft > 0) {
                Attendance::create([
                    'employee_id'       => $emp->id,
                    'iot_device_id'     => null,
                    'tanggal'           => $tanggal,
                    'jam_masuk'         => null,
                    'jam_keluar'        => null,
                    'metode'            => null,
                    'status'            => 'ABSENT',
                    'menit_terlambat'   => 0,
                    'absence_reason_id' => $reasonHospitalized?->id,
                    'is_auto_generated' => false,
                ]);
                $hospLeft--;
                continue;
            }

            // ABSENT menikah (sah)
            if ($marriageLeft > 0) {
                Attendance::create([
                    'employee_id'       => $emp->id,
                    'iot_device_id'     => null,
                    'tanggal'           => $tanggal,
                    'jam_masuk'         => null,
                    'jam_keluar'        => null,
                    'metode'            => null,
                    'status'            => 'ABSENT',
                    'menit_terlambat'   => 0,
                    'absence_reason_id' => $reasonMarriage?->id,
                    'is_auto_generated' => false,
                ]);
                $marriageLeft--;
                continue;
            }

            // ABSENT keluarga meninggal (sah)
            if ($familyDeathLeft > 0) {
                Attendance::create([
                    'employee_id'       => $emp->id,
                    'iot_device_id'     => null,
                    'tanggal'           => $tanggal,
                    'jam_masuk'         => null,
                    'jam_keluar'        => null,
                    'metode'            => null,
                    'status'            => 'ABSENT',
                    'menit_terlambat'   => 0,
                    'absence_reason_id' => $reasonFamilyDeath?->id,
                    'is_auto_generated' => false,
                ]);
                $familyDeathLeft--;
                continue;
            }

            // HADIR / TERLAMBAT
            $useFp  = ($fpLeft > 0);
            $metode = $useFp ? 'FINGERPRINT' : 'RFID';
            $device = $useFp ? $deviceFp : $deviceRfid;

            if ($telatLbh10Left > 0) {
                $menit  = rand(11, 59);
                $status = 'TERLAMBAT';
                $jam    = Carbon::parse($tanggal . ' 07:00:00')->addMinutes($menit)->format('H:i:s');
                $telatLbh10Left--;
            } elseif ($telat1_10Left > 0) {
                $menit  = rand(1, 10);
                $status = 'TERLAMBAT';
                $jam    = Carbon::parse($tanggal . ' 07:00:00')->addMinutes($menit)->format('H:i:s');
                $telat1_10Left--;
            } else {
                $menit  = 0;
                $status = 'HADIR';
                $jam    = Carbon::parse($tanggal . ' 06:30:00')->addMinutes(rand(0, 29))->format('H:i:s');
            }

            Attendance::create([
                'employee_id'       => $emp->id,
                'iot_device_id'     => $device->id,
                'tanggal'           => $tanggal,
                'jam_masuk'         => $jam,
                'jam_keluar'        => Carbon::parse($tanggal . ' 16:00:00')->addMinutes(rand(0, 90))->format('H:i:s'),
                'metode'            => $metode,
                'status'            => $status,
                'menit_terlambat'   => $menit,
                'absence_reason_id' => null,
                'is_auto_generated' => false,
            ]);

            if ($useFp) {
                $fpLeft--;
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 18 profil karyawan dengan perhitungan C2 yang benar.
    //
    // September 2026: 21 hari kerja (7 Sep = libur perusahaan)
    //
    // RUMUS TERVERIFIKASI:
    //   C1 = MAX(0, 100 - absent_tk * 60)
    //   C2 = MAX(0, 100 - t10*10 - tL*40)
    //   C3 = (hadir - FP) / hadir * 100  [jika hadir=0 → C3=0]
    //   Final% = C1n*50 + C2n*35 + C3n*15   (n = nilai/100)
    //
    // ─── POSITIF (≥90): 5 karyawan ──────────────────────────────────────────
    // E01: C1=100,C2=100,C3=100            → 50+35+15=100.00
    // E02: C1=100,C2=90(1x≤10mnt),C3=100  → 50+31.5+15=96.50
    // E03: C1=100,C2=80(2x≤10mnt),C3=100  → 50+28+15=93.00
    // E04: C1=100,C2=90(1x≤10mnt),         hadir=20,FP=3 → C3=(20-3)/20*100=85
    //      → 50+31.5+12.75=94.25
    // E05: C1=100,C2=70(3x≤10mnt),C3=100  → 50+24.5+15=89.50
    //
    // ─── PERTIMBANGAN (80–89): 4 karyawan ───────────────────────────────────
    // E06: C1=100,C2=60(1x>10mnt),C3=100  → 50+21+15=86.00
    // E07: C1=100,C2=60(1x>10mnt),         hadir=21,FP=6 → C3=(21-6)/21*100=71.4
    //      → 50+21+10.71=81.71
    // E08: C1=100,C2=50(1x≤10+1x>10),C3=100 → 50+17.5+15=82.50
    // E09: C1=100,C2=60(1x>10mnt),         hadir=20(1 absent sah),FP=3 → C3=85
    //      → 50+21+12.75=83.75
    //
    // ─── PERHATIAN (70–79): 5 karyawan ──────────────────────────────────────
    // E10: C1=40(1tk),C2=100,C3=100        → 20+35+15=70.00 (tepat batas)
    // E11: C1=100,C2=40(6x≤10mnt),C3=100  → 50+14+15=79.00
    // E12: C1=100,C2=30(7x≤10mnt),C3=100  → 50+10.5+15=75.50
    // E13: C1=100,C2=20(2x>10mnt),C3=100  → 50+7+15=72.00
    // E14: C1=100,C2=30(7x≤10mnt),         hadir=20(1 absent sah),FP=3 → C3=85
    //      → 50+10.5+12.75=73.25
    //
    // ─── EVALUASI KHUSUS (<70): 4 karyawan ──────────────────────────────────
    // E15: C1=40(1tk),C2=60(1x>10mnt),C3=100 → 20+21+15=56.00
    // E16: C1=0(2tk),C2=100,C3=100            → 0+35+15=50.00
    // E17: C1=40(1tk),C2=0(3x>10mnt=120→0),C3=100 → 20+0+15=35.00
    // E18: C1=0(2tk),C2=60(1x>10mnt),         hadir=19,FP=6 → C3=(19-6)/19*100=68.4
    //      → 0+21+10.26=31.26
    // ─────────────────────────────────────────────────────────────────────────
    private function employeeProfiles(): array
    {
        return [
            // ─── POSITIF (≥90) — 5 karyawan ─────────────────────────────────
            [
                'nama' => 'Budi Santoso', 'departemen' => 'IT', 'jabatan' => 'Backend Developer',
                'absent_tanpa_alasan' => 0, 'absent_hosp' => 0, 'absent_marriage' => 0, 'absent_family_death' => 0,
                'telat_1_10' => 0, 'telat_lebih10' => 0, 'fingerprint_days' => 0,
                'target_kategori' => 'positif', // 100.00
            ],
            [
                'nama' => 'Siti Rahayu', 'departemen' => 'IT', 'jabatan' => 'Frontend Developer',
                'absent_tanpa_alasan' => 0, 'absent_hosp' => 0, 'absent_marriage' => 0, 'absent_family_death' => 0,
                'telat_1_10' => 1, 'telat_lebih10' => 0, 'fingerprint_days' => 0,
                'target_kategori' => 'positif', // C2=90 → 96.50
            ],
            [
                'nama' => 'Ahmad Fauzi', 'departemen' => 'HRD', 'jabatan' => 'HR Specialist',
                'absent_tanpa_alasan' => 0, 'absent_hosp' => 0, 'absent_marriage' => 0, 'absent_family_death' => 0,
                'telat_1_10' => 2, 'telat_lebih10' => 0, 'fingerprint_days' => 0,
                'target_kategori' => 'positif', // C2=80 → 93.00
            ],
            [
                'nama' => 'Dewi Lestari', 'departemen' => 'HRD', 'jabatan' => 'HR Manager',
                'absent_tanpa_alasan' => 0, 'absent_hosp' => 1, 'absent_marriage' => 0, 'absent_family_death' => 0,
                'telat_1_10' => 1, 'telat_lebih10' => 0, 'fingerprint_days' => 3,
                'target_kategori' => 'positif', // C2=90,C3=85(3FP/20hadir) → 94.25
            ],
            [
                'nama' => 'Rudi Hartono', 'departemen' => 'Finance', 'jabatan' => 'Akuntan Senior',
                'absent_tanpa_alasan' => 0, 'absent_hosp' => 0, 'absent_marriage' => 0, 'absent_family_death' => 0,
                'telat_1_10' => 3, 'telat_lebih10' => 0, 'fingerprint_days' => 0,
                'target_kategori' => 'positif', // C2=70 → 89.50
            ],

            // ─── PERTIMBANGAN (80–89) — 4 karyawan ───────────────────────────
            [
                'nama' => 'Eko Prasetyo', 'departemen' => 'Finance', 'jabatan' => 'Staff Keuangan',
                'absent_tanpa_alasan' => 0, 'absent_hosp' => 0, 'absent_marriage' => 0, 'absent_family_death' => 0,
                'telat_1_10' => 0, 'telat_lebih10' => 1, 'fingerprint_days' => 0,
                'target_kategori' => 'pertimbangan', // C2=60(1x>10) → 86.00
            ],
            [
                'nama' => 'Maya Sari', 'departemen' => 'Finance', 'jabatan' => 'Bendahara',
                'absent_tanpa_alasan' => 0, 'absent_hosp' => 0, 'absent_marriage' => 0, 'absent_family_death' => 0,
                'telat_1_10' => 0, 'telat_lebih10' => 1, 'fingerprint_days' => 6,
                'target_kategori' => 'pertimbangan', // C2=60,C3=71(6FP/21) → 81.71
            ],
            [
                'nama' => 'Hendra Wijaya', 'departemen' => 'Marketing', 'jabatan' => 'Marketing Executive',
                'absent_tanpa_alasan' => 0, 'absent_hosp' => 0, 'absent_marriage' => 0, 'absent_family_death' => 0,
                'telat_1_10' => 1, 'telat_lebih10' => 1, 'fingerprint_days' => 0,
                'target_kategori' => 'pertimbangan', // C2=50(10+40) → 82.50
            ],
            [
                'nama' => 'Fitri Handayani', 'departemen' => 'Marketing', 'jabatan' => 'Brand Manager',
                'absent_tanpa_alasan' => 0, 'absent_hosp' => 0, 'absent_marriage' => 1, 'absent_family_death' => 0,
                'telat_1_10' => 0, 'telat_lebih10' => 1, 'fingerprint_days' => 3,
                'target_kategori' => 'pertimbangan', // C2=60,C3=85(3FP/20hadir) → 83.75
            ],

            // ─── PERHATIAN (70–79) — 5 karyawan ─────────────────────────────
            [
                'nama' => 'Dian Permata', 'departemen' => 'Marketing', 'jabatan' => 'Digital Strategist',
                'absent_tanpa_alasan' => 1, 'absent_hosp' => 0, 'absent_marriage' => 0, 'absent_family_death' => 0,
                'telat_1_10' => 0, 'telat_lebih10' => 0, 'fingerprint_days' => 0,
                'target_kategori' => 'perhatian', // C1=40,C2=100,C3=100 → 70.00
            ],
            [
                'nama' => 'Agus Setiawan', 'departemen' => 'Produksi', 'jabatan' => 'Supervisor Produksi',
                'absent_tanpa_alasan' => 0, 'absent_hosp' => 0, 'absent_marriage' => 0, 'absent_family_death' => 0,
                'telat_1_10' => 6, 'telat_lebih10' => 0, 'fingerprint_days' => 0,
                'target_kategori' => 'perhatian', // C2=40(6x10=60→C2=40) → 79.00
            ],
            [
                'nama' => 'Sri Wahyuni', 'departemen' => 'Produksi', 'jabatan' => 'Staff Produksi',
                'absent_tanpa_alasan' => 0, 'absent_hosp' => 0, 'absent_marriage' => 0, 'absent_family_death' => 0,
                'telat_1_10' => 7, 'telat_lebih10' => 0, 'fingerprint_days' => 0,
                'target_kategori' => 'perhatian', // C2=30(7x10=70→C2=30) → 75.50
            ],
            [
                'nama' => 'Lina Kusuma', 'departemen' => 'Produksi', 'jabatan' => 'Operator Mesin',
                'absent_tanpa_alasan' => 0, 'absent_hosp' => 0, 'absent_marriage' => 0, 'absent_family_death' => 0,
                'telat_1_10' => 0, 'telat_lebih10' => 2, 'fingerprint_days' => 0,
                'target_kategori' => 'perhatian', // C2=20(2x40=80→C2=20) → 72.00
            ],
            [
                'nama' => 'Yanto Prabowo', 'departemen' => 'Produksi', 'jabatan' => 'Teknisi Senior',
                'absent_tanpa_alasan' => 0, 'absent_hosp' => 1, 'absent_marriage' => 0, 'absent_family_death' => 0,
                'telat_1_10' => 7, 'telat_lebih10' => 0, 'fingerprint_days' => 3,
                'target_kategori' => 'perhatian', // C2=30,C3=85(3FP/20hadir) → 73.25
            ],

            // ─── EVALUASI KHUSUS (<70) — 4 karyawan ──────────────────────────
            [
                'nama' => 'Bambang Susilo', 'departemen' => 'Produksi', 'jabatan' => 'Teknisi',
                'absent_tanpa_alasan' => 1, 'absent_hosp' => 0, 'absent_marriage' => 0, 'absent_family_death' => 0,
                'telat_1_10' => 0, 'telat_lebih10' => 1, 'fingerprint_days' => 0,
                'target_kategori' => 'evaluasi_khusus', // C1=40,C2=60,C3=100 → 56.00
            ],
            [
                'nama' => 'Citra Dewi', 'departemen' => 'IT', 'jabatan' => 'QA Engineer',
                'absent_tanpa_alasan' => 2, 'absent_hosp' => 0, 'absent_marriage' => 0, 'absent_family_death' => 0,
                'telat_1_10' => 0, 'telat_lebih10' => 0, 'fingerprint_days' => 0,
                'target_kategori' => 'evaluasi_khusus', // C1=0,C2=100,C3=100 → 50.00
            ],
            [
                'nama' => 'Dodi Kurniawan', 'departemen' => 'HRD', 'jabatan' => 'Recruiter',
                'absent_tanpa_alasan' => 1, 'absent_hosp' => 0, 'absent_marriage' => 0, 'absent_family_death' => 0,
                'telat_1_10' => 0, 'telat_lebih10' => 3, 'fingerprint_days' => 0,
                'target_kategori' => 'evaluasi_khusus', // C1=40,C2=0(3x40=120→0) → 35.00
            ],
            [
                'nama' => 'Rina Melati', 'departemen' => 'Marketing', 'jabatan' => 'Sales Representative',
                'absent_tanpa_alasan' => 2, 'absent_hosp' => 0, 'absent_marriage' => 0, 'absent_family_death' => 0,
                'telat_1_10' => 0, 'telat_lebih10' => 1, 'fingerprint_days' => 6,
                'target_kategori' => 'evaluasi_khusus', // C1=0,C2=60,C3=68(6FP/19) → 31.26
            ],
        ];
    }
}
