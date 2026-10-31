<?php

namespace Tests\Feature;

use App\Models\AbsenceReason;
use App\Models\Attendance;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\EvaluationPeriod;
use App\Models\SpkCriteria;
use App\Models\SpkEvaluation;
use App\Models\SpkEvaluationDetail;
use App\Models\User;
use App\Services\SawCalculator;
use App\Services\SpkCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test baru untuk item 1-5 sesuai permintaan:
 * - GET /members → 18 anggota untuk periode grup utama (disimulasikan dengan 18 kontrak)
 * - GET /evaluations/{id} → langkah_saw, data_mentah, bukti_absensi
 * - Konsistensi: 100 - jumlah_poin = nilai_mentah (kecuali dibatasi)
 * - Kasus: 6 telat 1-10 menit, hadir 21 via RFID
 * - Kasus: absen sah (poin 0), fingerprint (poin 100/hadir), dibatasi minimal 0
 */
class EvaluationTransparencyTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Employee $employee;
    protected Contract $contract;
    protected EvaluationPeriod $period;
    protected SpkCriteria $c1;
    protected SpkCriteria $c2;
    protected SpkCriteria $c3;
    protected AbsenceReason $alasanSah;
    protected AbsenceReason $alasanTidakSah;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->c1 = SpkCriteria::create(['kode' => 'C1', 'nama' => 'Kehadiran',         'tipe' => 'benefit', 'bobot' => 50.00]);
        $this->c2 = SpkCriteria::create(['kode' => 'C2', 'nama' => 'Ketepatan Waktu',   'tipe' => 'benefit', 'bobot' => 35.00]);
        $this->c3 = SpkCriteria::create(['kode' => 'C3', 'nama' => 'Kepatuhan ID Card', 'tipe' => 'benefit', 'bobot' => 15.00]);

        $this->alasanSah     = AbsenceReason::create(['kode' => 'MARRIAGE',  'nama' => 'Menikah',           'is_sah' => true]);
        $this->alasanTidakSah = AbsenceReason::create(['kode' => 'NO_REASON', 'nama' => 'Tanpa Keterangan', 'is_sah' => false]);

        $this->employee = Employee::create([
            'nama'        => 'Agus Test',
            'nip'         => 'T001',
            'jabatan'     => 'Staff',
            'departemen'  => 'IT',
            'status_aktif' => true,
        ]);

        $this->contract = Contract::create([
            'employee_id'   => $this->employee->id,
            'tanggal_mulai'  => '2026-09-01',
            'tanggal_selesai' => '2027-03-31',
            'status'         => 'aktif',
        ]);

        $this->period = EvaluationPeriod::create([
            'kontrak_mulai'   => '2026-09-01',
            'kontrak_selesai' => '2027-03-31',
            'tanggal_mulai'   => '2026-09-01',
            'tanggal_selesai' => '2026-09-30',
            'status'          => 'selesai',
        ]);
    }

    // ════════════════════════════════════════════════════════════════════
    //  KASUS UTAMA: 6 telat 1-10 mnt, 21 hari hadir RFID
    //  Ekspektasi: bukti 6 baris TELAT_1_10, jumlah poin C2 = 60,
    //              nilai_mentah C2 = 40, rumus memuat angka 6
    // ════════════════════════════════════════════════════════════════════
    public function test_kasus_6_telat_21_hadir_rfid()
    {
        // 6 hari telat 1-10 menit (1-6 Sep)
        for ($i = 1; $i <= 6; $i++) {
            Attendance::create([
                'employee_id'    => $this->employee->id,
                'tanggal'        => "2026-09-0{$i}",
                'jam_masuk'      => '07:07:00',
                'status'         => 'TERLAMBAT',
                'metode'         => 'RFID',
                'menit_terlambat' => 7,
                'is_auto_generated' => false,
            ]);
        }
        // 15 hari hadir tepat waktu via RFID (7-21 Sep)
        for ($i = 7; $i <= 21; $i++) {
            Attendance::create([
                'employee_id'    => $this->employee->id,
                'tanggal'        => "2026-09-" . str_pad($i, 2, '0', STR_PAD_LEFT),
                'jam_masuk'      => '06:55:00',
                'status'         => 'HADIR',
                'metode'         => 'RFID',
                'menit_terlambat' => 0,
                'is_auto_generated' => false,
            ]);
        }

        // Hitung SPK
        $service = new SpkCalculationService(new SawCalculator());
        $service->calculateGroup($this->period);

        $eval = SpkEvaluation::where('evaluation_period_id', $this->period->id)->first();

        // Nilai mentah C2 harus 40
        $detailC2 = $eval->details()->whereHas('spkCriteria', fn($q) => $q->where('kode', 'C2'))->first();
        $this->assertEquals(40.0, (float) $detailC2->nilai_mentah, 'C2 nilai_mentah harus 40');

        // Verifikasi via API
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/evaluations/{$eval->id}");
        $response->assertStatus(200);

        // ── data_mentah ──────────────────────────────────────────────────────
        $dataMentah = $response->json('data_mentah');
        $this->assertEquals(6,  $dataMentah['telat_1_10'],    'data_mentah.telat_1_10 harus 6');
        $this->assertEquals(0,  $dataMentah['telat_lebih_10'], 'data_mentah.telat_lebih_10 harus 0');
        $this->assertEquals(21, $dataMentah['total_hadir'],   'data_mentah.total_hadir harus 21');
        $this->assertEquals(0,  $dataMentah['pakai_fingerprint'], 'data_mentah.pakai_fingerprint harus 0');
        $this->assertEquals(0,  $dataMentah['absent_tanpa_keterangan']);
        $this->assertEquals(0,  $dataMentah['absent_sah']);

        // ── bukti_absensi: harus tepat 6 baris TELAT_1_10 ───────────────────
        $bukti = $response->json('bukti_absensi');
        $telatBukti = array_filter($bukti, fn($b) => $b['jenis'] === 'TELAT_1_10');
        $this->assertCount(6, $telatBukti, 'bukti_absensi harus punya tepat 6 baris TELAT_1_10');

        // jumlah poin C2 dari bukti
        $totalPoinC2 = array_sum(array_column(
            array_filter($bukti, fn($b) => $b['kriteria'] === 'C2'),
            'poin_penalti'
        ));
        $this->assertEquals(60, $totalPoinC2, 'Total poin C2 harus 60 (6 * 10)');

        // ── rumus_substitusi C2 harus mengandung angka 6 ─────────────────────
        $langkahC2 = collect($response->json('langkah_saw.langkah'))->firstWhere('kode', 'C2');
        $this->assertStringContainsString('6', $langkahC2['rumus_substitusi'], 'rumus_substitusi C2 harus mengandung angka 6');

        // ── Konsistensi: 100 - jumlah_poin = nilai_mentah (C1, C2) ──────────
        $langkah = $response->json('langkah_saw.langkah');
        foreach ($langkah as $step) {
            if (in_array($step['kode'], ['C1', 'C2'])) {
                $dibatasi = $step['dibatasi'] ?? false;
                if (!$dibatasi) {
                    // Hitung poin dari bukti
                    $poin = array_sum(array_column(
                        array_filter($bukti, fn($b) => $b['kriteria'] === $step['kode']),
                        'poin_penalti'
                    ));
                    $expected = max(0, 100 - $poin);
                    $this->assertEqualsWithDelta(
                        $expected,
                        $step['nilai_mentah'],
                        0.01,
                        "Konsistensi gagal untuk {$step['kode']}: 100 - $poin = $expected, tapi nilai_mentah = {$step['nilai_mentah']}"
                    );
                }
            }
        }
    }

    // ════════════════════════════════════════════════════════════════════
    //  KASUS ABSEN SAH: poin 0, tetap muncul di bukti_absensi
    // ════════════════════════════════════════════════════════════════════
    public function test_kasus_absen_sah_poin_nol()
    {
        // 1 hari absen sah
        Attendance::create([
            'employee_id'       => $this->employee->id,
            'tanggal'           => '2026-09-01',
            'status'            => 'ABSENT',
            'metode'            => null,
            'absence_reason_id' => $this->alasanSah->id,
            'menit_terlambat'   => 0,
            'is_auto_generated' => true,
        ]);
        // 5 hari hadir RFID
        for ($i = 2; $i <= 6; $i++) {
            Attendance::create([
                'employee_id'    => $this->employee->id,
                'tanggal'        => "2026-09-0{$i}",
                'jam_masuk'      => '06:55:00',
                'status'         => 'HADIR',
                'metode'         => 'RFID',
                'menit_terlambat' => 0,
                'is_auto_generated' => false,
            ]);
        }

        $service = new SpkCalculationService(new SawCalculator());
        $service->calculateGroup($this->period);

        $eval = SpkEvaluation::where('evaluation_period_id', $this->period->id)->first();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/evaluations/{$eval->id}");
        $response->assertStatus(200);

        $bukti = $response->json('bukti_absensi');

        // Harus ada entry ABSEN_SAH
        $absenSahBukti = array_filter($bukti, fn($b) => $b['jenis'] === 'ABSEN_SAH');
        $this->assertCount(1, $absenSahBukti, 'Harus ada 1 baris ABSEN_SAH di bukti_absensi');

        // Poin ABSEN_SAH harus 0
        foreach ($absenSahBukti as $item) {
            $this->assertEquals(0, $item['poin_penalti'], 'Poin ABSEN_SAH harus 0');
        }

        // C1 nilai_mentah harus 100 (absen sah tidak diperhitungkan)
        $langkahC1 = collect($response->json('langkah_saw.langkah'))->firstWhere('kode', 'C1');
        $this->assertEquals(100.0, $langkahC1['nilai_mentah'], 'Absen sah tidak boleh mempengaruhi C1');
    }

    // ════════════════════════════════════════════════════════════════════
    //  KASUS FINGERPRINT: poin = 100 / total_hadir
    // ════════════════════════════════════════════════════════════════════
    public function test_kasus_fingerprint_poin_per_hadir()
    {
        // 10 hari hadir: 3 fingerprint, 7 RFID
        for ($i = 1; $i <= 10; $i++) {
            $metode = $i <= 3 ? 'FINGERPRINT' : 'RFID';
            Attendance::create([
                'employee_id'    => $this->employee->id,
                'tanggal'        => "2026-09-" . str_pad($i, 2, '0', STR_PAD_LEFT),
                'jam_masuk'      => '06:55:00',
                'status'         => 'HADIR',
                'metode'         => $metode,
                'menit_terlambat' => 0,
                'is_auto_generated' => false,
            ]);
        }

        $service = new SpkCalculationService(new SawCalculator());
        $service->calculateGroup($this->period);

        $eval = SpkEvaluation::where('evaluation_period_id', $this->period->id)->first();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/evaluations/{$eval->id}");
        $response->assertStatus(200);

        $bukti = $response->json('bukti_absensi');

        // Harus ada 3 baris FINGERPRINT
        $fpBukti = array_filter($bukti, fn($b) => $b['jenis'] === 'FINGERPRINT');
        $this->assertCount(3, $fpBukti, 'Harus ada 3 baris FINGERPRINT di bukti_absensi');

        // Poin fingerprint = 100 / 10 = 10.0 per baris
        foreach ($fpBukti as $item) {
            $this->assertEqualsWithDelta(10.0, $item['poin_penalti'], 0.01, 'Poin FINGERPRINT harus 100/10 = 10');
        }

        // Konsistensi C3: total_hadir=10, pakai_fingerprint=3
        // nilai_mentah C3 = (10-3)/10*100 = 70
        $langkahC3 = collect($response->json('langkah_saw.langkah'))->firstWhere('kode', 'C3');
        $this->assertEqualsWithDelta(70.0, $langkahC3['nilai_mentah'], 0.01, 'C3 nilai_mentah harus 70');
    }

    // ════════════════════════════════════════════════════════════════════
    //  KASUS DIBATASI: 3 absen tanpa keterangan → poin C1 = 180 → dibatasi 0
    // ════════════════════════════════════════════════════════════════════
    public function test_kasus_dibatasi_minimal_0()
    {
        // 3 absen tanpa keterangan → C1 = MAX(0, 100 - 3*60) = MAX(0, -80) = 0
        for ($i = 1; $i <= 3; $i++) {
            Attendance::create([
                'employee_id'       => $this->employee->id,
                'tanggal'           => "2026-09-0{$i}",
                'status'            => 'ABSENT',
                'metode'            => null,
                'absence_reason_id' => null, // tanpa keterangan
                'menit_terlambat'   => 0,
                'is_auto_generated' => true,
            ]);
        }
        // 5 hari hadir RFID
        for ($i = 4; $i <= 8; $i++) {
            Attendance::create([
                'employee_id'    => $this->employee->id,
                'tanggal'        => "2026-09-0{$i}",
                'jam_masuk'      => '06:55:00',
                'status'         => 'HADIR',
                'metode'         => 'RFID',
                'menit_terlambat' => 0,
                'is_auto_generated' => false,
            ]);
        }

        $service = new SpkCalculationService(new SawCalculator());
        $service->calculateGroup($this->period);

        $eval = SpkEvaluation::where('evaluation_period_id', $this->period->id)->first();

        // C1 harus 0 (dibatasi)
        $detailC1 = $eval->details()->whereHas('spkCriteria', fn($q) => $q->where('kode', 'C1'))->first();
        $this->assertEquals(0.0, (float) $detailC1->nilai_mentah, 'C1 harus 0 saat poin > 100');

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/evaluations/{$eval->id}");
        $response->assertStatus(200);

        // Field dibatasi harus true di C1
        $langkahC1 = collect($response->json('langkah_saw.langkah'))->firstWhere('kode', 'C1');
        $this->assertTrue($langkahC1['dibatasi'] ?? false, 'Field dibatasi harus true di langkah C1');
    }

    // ════════════════════════════════════════════════════════════════════
    //  KASUS total_hadir = 0: C3 = 0, tidak ada baris FINGERPRINT
    // ════════════════════════════════════════════════════════════════════
    public function test_kasus_hadir_0_c3_nol_tanpa_fingerprint()
    {
        // Hanya 1 absen sah (hadir = 0)
        Attendance::create([
            'employee_id'       => $this->employee->id,
            'tanggal'           => '2026-09-01',
            'status'            => 'ABSENT',
            'absence_reason_id' => $this->alasanSah->id,
            'menit_terlambat'   => 0,
            'is_auto_generated' => true,
        ]);

        $service = new SpkCalculationService(new SawCalculator());
        $service->calculateGroup($this->period);

        $eval = SpkEvaluation::where('evaluation_period_id', $this->period->id)->first();
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/evaluations/{$eval->id}");
        $response->assertStatus(200);

        $bukti = $response->json('bukti_absensi');
        $fpBukti = array_filter($bukti, fn($b) => $b['jenis'] === 'FINGERPRINT');
        $this->assertCount(0, $fpBukti, 'Hadir 0 → tidak boleh ada baris FINGERPRINT di bukti_absensi');

        $langkahC3 = collect($response->json('langkah_saw.langkah'))->firstWhere('kode', 'C3');
        $this->assertEquals(0.0, $langkahC3['nilai_mentah'], 'Hadir 0 → C3 harus 0');
    }

    // ════════════════════════════════════════════════════════════════════
    //  GET /members: harus mengembalikan semua anggota grup
    //  Test dengan 18 kontrak dalam grup yang sama
    // ════════════════════════════════════════════════════════════════════
    public function test_members_returns_all_group_members()
    {
        // Period sudah dibuat di setUp dengan 1 kontrak (employee ini)
        // Tambah 17 lagi untuk total 18
        for ($i = 2; $i <= 18; $i++) {
            $emp = Employee::create([
                'nama'        => "Karyawan {$i}",
                'nip'         => "EMP{$i}",
                'jabatan'     => 'Staff',
                'status_aktif' => true,
            ]);
            Contract::create([
                'employee_id'    => $emp->id,
                'tanggal_mulai'  => '2026-09-01',
                'tanggal_selesai' => '2027-03-31',
                'status'         => 'aktif',
            ]);
        }

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/evaluation-periods/{$this->period->id}/members");

        $response->assertStatus(200);
        $response->assertJsonPath('total', 18);
        $response->assertJsonCount(18, 'data');

        // Struktur item pertama
        $response->assertJsonStructure([
            'evaluation_period_id',
            'kontrak_mulai',
            'kontrak_selesai',
            'total',
            'data' => [
                '*' => [
                    'employee_id', 'contract_id', 'nip', 'nama',
                    'departemen', 'jabatan', 'sudah_dihitung',
                    'nilai_akhir', 'kategori', 'spk_evaluation_id',
                ],
            ],
        ]);

        // sudah_dihitung harus false karena belum ada kalkulasi
        $data = $response->json('data');
        foreach ($data as $member) {
            $this->assertFalse($member['sudah_dihitung'], 'sudah_dihitung harus false sebelum kalkulasi');
        }
    }

    // ════════════════════════════════════════════════════════════════════
    //  GET /members: setelah kalkulasi, sudah_dihitung = true
    // ════════════════════════════════════════════════════════════════════
    public function test_members_sudah_dihitung_setelah_kalkulasi()
    {
        Attendance::create([
            'employee_id'    => $this->employee->id,
            'tanggal'        => '2026-09-01',
            'jam_masuk'      => '07:00:00',
            'status'         => 'HADIR',
            'metode'         => 'RFID',
            'menit_terlambat' => 0,
            'is_auto_generated' => false,
        ]);

        $service = new SpkCalculationService(new SawCalculator());
        $service->calculateGroup($this->period);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/evaluation-periods/{$this->period->id}/members");

        $response->assertStatus(200);
        $data = $response->json('data');
        $member = collect($data)->firstWhere('employee_id', $this->employee->id);

        $this->assertTrue($member['sudah_dihitung'], 'sudah_dihitung harus true setelah kalkulasi');
        $this->assertNotNull($member['nilai_akhir'], 'nilai_akhir harus terisi');
        $this->assertNotNull($member['kategori'], 'kategori harus terisi');
    }

    // ════════════════════════════════════════════════════════════════════
    //  GET /evaluation-periods: TIDAK boleh ada field spk_evaluations
    // ════════════════════════════════════════════════════════════════════
    public function test_index_tidak_eager_load_spk_evaluations()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/evaluation-periods');

        $response->assertStatus(200);

        $data = $response->json('data');
        if (!empty($data)) {
            foreach ($data as $item) {
                $this->assertArrayNotHasKey(
                    'spk_evaluations',
                    $item,
                    'Response /evaluation-periods TIDAK boleh menyertakan spk_evaluations'
                );
            }
        }
    }

    // ════════════════════════════════════════════════════════════════════
    //  Konsistensi: 100 - jumlah_poin_dari_bukti = nilai_mentah
    //  untuk kasus normal (tidak dibatasi)
    // ════════════════════════════════════════════════════════════════════
    public function test_konsistensi_poin_vs_nilai_mentah()
    {
        // 2 telat 1-10 mnt, 1 telat >10 mnt, 5 hadir RFID
        Attendance::create(['employee_id' => $this->employee->id, 'tanggal' => '2026-09-01',
            'jam_masuk' => '07:05:00', 'status' => 'TERLAMBAT', 'metode' => 'RFID', 'menit_terlambat' => 5, 'is_auto_generated' => false]);
        Attendance::create(['employee_id' => $this->employee->id, 'tanggal' => '2026-09-02',
            'jam_masuk' => '07:08:00', 'status' => 'TERLAMBAT', 'metode' => 'RFID', 'menit_terlambat' => 8, 'is_auto_generated' => false]);
        Attendance::create(['employee_id' => $this->employee->id, 'tanggal' => '2026-09-03',
            'jam_masuk' => '07:20:00', 'status' => 'TERLAMBAT', 'metode' => 'RFID', 'menit_terlambat' => 20, 'is_auto_generated' => false]);
        for ($i = 4; $i <= 8; $i++) {
            Attendance::create(['employee_id' => $this->employee->id, 'tanggal' => "2026-09-0{$i}",
                'jam_masuk' => '06:55:00', 'status' => 'HADIR', 'metode' => 'RFID', 'menit_terlambat' => 0, 'is_auto_generated' => false]);
        }

        // C2 = 100 - (2*10 + 1*40) = 40
        $service = new SpkCalculationService(new SawCalculator());
        $service->calculateGroup($this->period);
        $eval = SpkEvaluation::where('evaluation_period_id', $this->period->id)->first();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/evaluations/{$eval->id}");
        $response->assertStatus(200);

        $bukti   = $response->json('bukti_absensi');
        $langkah = $response->json('langkah_saw.langkah');

        foreach (['C1', 'C2'] as $kode) {
            $step = collect($langkah)->firstWhere('kode', $kode);
            if (!($step['dibatasi'] ?? false)) {
                $totalPoin = array_sum(array_column(
                    array_filter($bukti, fn($b) => $b['kriteria'] === $kode),
                    'poin_penalti'
                ));
                $expectedNilai = max(0, 100 - $totalPoin);
                $this->assertEqualsWithDelta(
                    $expectedNilai,
                    $step['nilai_mentah'],
                    0.01,
                    "Konsistensi {$kode}: 100 - {$totalPoin} = {$expectedNilai}, actual={$step['nilai_mentah']}"
                );
            }
        }

        // C2 harus 40
        $langkahC2 = collect($langkah)->firstWhere('kode', 'C2');
        $this->assertEqualsWithDelta(40.0, $langkahC2['nilai_mentah'], 0.01);
    }

    // ════════════════════════════════════════════════════════════════════
    //  langkah_saw: field wajib ada semua, nilai_akhir_persen konsisten
    // ════════════════════════════════════════════════════════════════════
    public function test_langkah_saw_struktur_dan_nilai_akhir()
    {
        Attendance::create([
            'employee_id'    => $this->employee->id,
            'tanggal'        => '2026-09-01',
            'jam_masuk'      => '07:00:00',
            'status'         => 'HADIR',
            'metode'         => 'RFID',
            'menit_terlambat' => 0,
            'is_auto_generated' => false,
        ]);

        $service = new SpkCalculationService(new SawCalculator());
        $service->calculateGroup($this->period);
        $eval = SpkEvaluation::where('evaluation_period_id', $this->period->id)->first();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/evaluations/{$eval->id}");
        $response->assertStatus(200);

        // Struktur langkah_saw
        $response->assertJsonStructure([
            'langkah_saw' => [
                'langkah' => [
                    '*' => ['kode', 'nama', 'nilai_mentah', 'rumus_substitusi', 'normalisasi', 'bobot', 'kontribusi'],
                ],
                'total_kontribusi',
                'nilai_akhir_persen',
                'kategori',
            ],
        ]);

        // nilai_akhir_persen harus konsisten dengan total_kontribusi * 100
        $totalKontribusi = $response->json('langkah_saw.total_kontribusi');
        $nilaiAkhirPersen = $response->json('langkah_saw.nilai_akhir_persen');
        $this->assertEqualsWithDelta(
            round($totalKontribusi * 100, 2),
            $nilaiAkhirPersen,
            0.01,
            'nilai_akhir_persen harus = total_kontribusi * 100'
        );

        // nilai_akhir di root harus sama dengan nilai_akhir_persen dari langkah_saw
        $this->assertEqualsWithDelta(
            (float) $response->json('nilai_akhir'),
            $nilaiAkhirPersen,
            0.01,
            'nilai_akhir root harus sama dengan langkah_saw.nilai_akhir_persen'
        );
    }

    // ════════════════════════════════════════════════════════════════════
    //  Format tanggal: bukti_absensi.tanggal harus Y-m-d, bukan ISO UTC
    // ════════════════════════════════════════════════════════════════════
    public function test_bukti_absensi_tanggal_format_ymid()
    {
        Attendance::create([
            'employee_id'    => $this->employee->id,
            'tanggal'        => '2026-09-15',
            'status'         => 'ABSENT',
            'absence_reason_id' => null,
            'menit_terlambat'   => 0,
            'is_auto_generated' => true,
        ]);

        $service = new SpkCalculationService(new SawCalculator());
        $service->calculateGroup($this->period);
        $eval = SpkEvaluation::where('evaluation_period_id', $this->period->id)->first();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/evaluations/{$eval->id}");
        $response->assertStatus(200);

        $bukti = $response->json('bukti_absensi');
        $this->assertNotEmpty($bukti);
        // Tanggal harus format Y-m-d (bukan ada 'T' atau 'Z')
        foreach ($bukti as $item) {
            $this->assertMatchesRegularExpression(
                '/^\d{4}-\d{2}-\d{2}$/',
                $item['tanggal'],
                "bukti_absensi.tanggal harus format Y-m-d, dapat: {$item['tanggal']}"
            );
        }
    }

    // ════════════════════════════════════════════════════════════════════
    //  members kontrak_mulai dan kontrak_selesai harus Y-m-d
    // ════════════════════════════════════════════════════════════════════
    public function test_members_format_tanggal()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/evaluation-periods/{$this->period->id}/members");

        $response->assertStatus(200);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $response->json('kontrak_mulai'));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $response->json('kontrak_selesai'));
    }
}
