<?php

namespace Tests\Feature;

use App\Models\AbsenceReason;
use App\Models\Attendance;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\EvaluationPeriod;
use App\Models\SpkCriteria;
use App\Services\SawCalculator;
use App\Services\SpkCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpkCalculationFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed Criterias
        SpkCriteria::create(['kode' => 'C1', 'nama' => 'Kehadiran', 'tipe' => 'benefit', 'bobot' => 50.00]);
        SpkCriteria::create(['kode' => 'C2', 'nama' => 'Ketepatan Waktu', 'tipe' => 'benefit', 'bobot' => 35.00]);
        SpkCriteria::create(['kode' => 'C3', 'nama' => 'Kepatuhan ID Card', 'tipe' => 'benefit', 'bobot' => 15.00]);

        $this->employee = Employee::create([
            'nama' => 'John',
            'nip' => 'E1',
            'jabatan' => 'Staff',
            'status_aktif' => true,
        ]);

        $this->contract = Contract::create([
            'employee_id' => $this->employee->id,
            'tanggal_mulai' => '2026-01-01',
            'tanggal_selesai' => '2026-01-31',
            'status' => 'aktif'
        ]);

        $this->period = EvaluationPeriod::create([
            'kontrak_mulai' => '2026-01-01',
            'kontrak_selesai' => '2026-01-31',
            'tanggal_mulai' => '2026-01-01',
            'tanggal_selesai' => '2026-01-31',
            'status' => 'berjalan'
        ]);

        $this->alasanSah = AbsenceReason::create(['kode' => 'S', 'nama' => 'Sakit', 'is_sah' => true]);
        $this->alasanTidakSah = AbsenceReason::create(['kode' => 'M', 'nama' => 'Mangkir', 'is_sah' => false]);
    }

    public function test_calculate_with_seed_attendance_produces_consistent_result()
    {
        // 1 absent tanpa keterangan (C1 = 40)
        Attendance::create([
            'employee_id' => $this->employee->id,
            'tanggal' => '2026-01-01',
            'waktu_masuk' => null,
            'waktu_keluar' => null,
            'status' => 'ABSENT',
            'metode' => null,
            'menit_terlambat' => 0,
            'absence_reason_id' => null,
            'is_auto_generated' => true
        ]);

        // 1 absent sah (C1 tetap 100, tidak mengurangi)
        Attendance::create([
            'employee_id' => $this->employee->id,
            'tanggal' => '2026-01-02',
            'waktu_masuk' => null,
            'waktu_keluar' => null,
            'status' => 'ABSENT',
            'metode' => null,
            'menit_terlambat' => 0,
            'absence_reason_id' => $this->alasanSah->id,
            'is_auto_generated' => true
        ]);

        // 2 telat 5 menit (1-10 mnt -> penalti 10 per kali)
        for ($i = 3; $i <= 4; $i++) {
            Attendance::create([
                'employee_id' => $this->employee->id,
                'tanggal' => "2026-01-0{$i}",
                'waktu_masuk' => '07:05:00',
                'waktu_keluar' => '17:00:00',
                'status' => 'TERLAMBAT',
                'metode' => 'RFID',
                'menit_terlambat' => 5,
                'absence_reason_id' => null,
                'is_auto_generated' => false
            ]);
        }

        // 1 telat 20 menit (>10 mnt -> penalti 40 per kali)
        // Total C2 = 100 - (2*10 + 1*40) = 40
        Attendance::create([
            'employee_id' => $this->employee->id,
            'tanggal' => '2026-01-05',
            'waktu_masuk' => '07:20:00',
            'waktu_keluar' => '17:00:00',
            'status' => 'TERLAMBAT',
            'metode' => 'RFID', // pakain RFID berarti tidak pakai fingerprint
            'menit_terlambat' => 20,
            'absence_reason_id' => null,
            'is_auto_generated' => false
        ]);

        // 17 hari hadir tepat waktu (0 menit telat) 
        // 3 hari pakai fingerprint, sisanya RFID
        // Total hadir = 17 + 3 telat = 20
        // Total pakai_fingerprint = 3
        // C3 = (20 - 3) / 20 * 100 = 85
        for ($i = 6; $i <= 22; $i++) {
            $metode = ($i <= 8) ? 'FINGERPRINT' : 'RFID';
            Attendance::create([
                'employee_id' => $this->employee->id,
                'tanggal' => "2026-01-" . str_pad($i, 2, '0', STR_PAD_LEFT),
                'waktu_masuk' => '06:50:00',
                'waktu_keluar' => '17:00:00',
                'status' => 'HADIR',
                'metode' => $metode,
                'menit_terlambat' => 0,
                'absence_reason_id' => null,
                'is_auto_generated' => false
            ]);
        }

        // C1 = 40, normalisasi 0.4. Kontribusi = 0.4 * 0.5 = 0.2
        // C2 = 40, normalisasi 0.4. Kontribusi = 0.4 * 0.35 = 0.14
        // C3 = 85, normalisasi 0.85. Kontribusi = 0.85 * 0.15 = 0.1275
        // Nilai Akhir = (0.2 + 0.14 + 0.1275) * 100 = 46.75 -> Evaluasi Khusus

        $service = new SpkCalculationService(new SawCalculator());
        $result = $service->calculateGroup($this->period);

        $this->assertArrayHasKey('jumlah_karyawan', $result);
        $this->assertCount(1, $result['warnings']); // Ada 1 warning karena 1 absent tanpa keterangan

        $this->assertDatabaseHas('spk_evaluations', [
            'evaluation_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'nilai_akhir' => 46.75,
            'kategori' => 'evaluasi_khusus'  // slug benar (bukan 'khusus')
        ]);

        $evaluation = $this->period->spkEvaluations()->first();

        // Assert Details C1
        $c1 = $evaluation->details()->whereHas('spkCriteria', function ($q) { $q->where('kode', 'C1'); })->first();
        $this->assertEquals(40.0, $c1->nilai_mentah);
        $this->assertEquals(0.4, $c1->nilai_normalisasi);
        $this->assertEquals(0.5, $c1->bobot);
        $this->assertEquals(0.2, $c1->kontribusi);

        // Assert Details C2
        $c2 = $evaluation->details()->whereHas('spkCriteria', function ($q) { $q->where('kode', 'C2'); })->first();
        $this->assertEquals(40.0, $c2->nilai_mentah);
        $this->assertEquals(0.4, $c2->nilai_normalisasi);
        $this->assertEquals(0.35, $c2->bobot);
        $this->assertEquals(0.14, $c2->kontribusi);

        // Assert Details C3
        $c3 = $evaluation->details()->whereHas('spkCriteria', function ($q) { $q->where('kode', 'C3'); })->first();
        $this->assertEquals(85.0, $c3->nilai_mentah);
        $this->assertEquals(0.85, $c3->nilai_normalisasi);
        $this->assertEquals(0.15, $c3->bobot);
        $this->assertEquals(0.1275, $c3->kontribusi);
    }

    public function test_hadir_0_returns_0_for_c3()
    {
        Attendance::create([
            'employee_id' => $this->employee->id,
            'tanggal' => '2026-01-01',
            'waktu_masuk' => null,
            'waktu_keluar' => null,
            'status' => 'ABSENT',
            'metode' => null,
            'menit_terlambat' => 0,
            'absence_reason_id' => $this->alasanSah->id,
            'is_auto_generated' => true
        ]);

        $service = new SpkCalculationService(new SawCalculator());
        $service->calculateGroup($this->period);

        $evaluation = $this->period->fresh()->spkEvaluations()->first();
        $c3 = $evaluation->details()->whereHas('spkCriteria', function ($q) { $q->where('kode', 'C3'); })->first();
        
        $this->assertEquals(0.0, $c3->nilai_mentah);
        $jumlahDataMentah = $c3->jumlah_data_mentah;
        $this->assertArrayHasKey('catatan', $jumlahDataMentah);
        $this->assertEquals('Hadir 0, C3 otomatis 0', $jumlahDataMentah['catatan']);
    }
}
