<?php

namespace Tests\Feature;

use App\Models\AbsenceReason;
use App\Models\Attendance;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\EvaluationPeriod;
use App\Models\HrdDecision;
use App\Models\SpkCriteria;
use App\Models\SpkEvaluation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test suite untuk POST /api/evaluations/{id}/recalculate
 *
 * Semua test memakai RefreshDatabase sehingga tidak menyentuh database spk_absensi
 * (database utama). Hitungan spk_evaluations, hrd_decisions, employees tetap 21/1/25.
 */
class RecalculateEvaluationTest extends TestCase
{
    use RefreshDatabase;

    // ── Setup ─────────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // Criteria baku (bobot sama dengan project rules)
        SpkCriteria::create(['kode' => 'C1', 'nama' => 'Kehadiran',        'tipe' => 'benefit', 'bobot' => 50.00]);
        SpkCriteria::create(['kode' => 'C2', 'nama' => 'Ketepatan Waktu',  'tipe' => 'benefit', 'bobot' => 35.00]);
        SpkCriteria::create(['kode' => 'C3', 'nama' => 'Kepatuhan ID Card','tipe' => 'benefit', 'bobot' => 15.00]);

        $this->alasanSah = AbsenceReason::create(['kode' => 'S', 'nama' => 'Sakit',  'is_sah' => true]);
        $this->alasanTS  = AbsenceReason::create(['kode' => 'M', 'nama' => 'Mangkir','is_sah' => false]);

        // Karyawan dan kontrak
        $this->employee = Employee::create([
            'nama'        => 'Andi',
            'nip'         => 'E001',
            'jabatan'     => 'Staff',
            'status_aktif' => true,
        ]);

        $this->contract = Contract::create([
            'employee_id'     => $this->employee->id,
            'tanggal_mulai'   => '2026-02-01',
            'tanggal_selesai' => '2026-02-28',
            'status'          => 'aktif',
        ]);

        $this->period = EvaluationPeriod::create([
            'kontrak_mulai'   => '2026-02-01',
            'kontrak_selesai' => '2026-02-28',
            'tanggal_mulai'   => '2026-02-01',
            'tanggal_selesai' => '2026-02-28',
            'status'          => 'berjalan',
        ]);

        // Karyawan kedua (hanya untuk test isolasi)
        $this->otherEmployee = Employee::create([
            'nama'        => 'Budi',
            'nip'         => 'E002',
            'jabatan'     => 'Staff',
            'status_aktif' => true,
        ]);

        $this->otherContract = Contract::create([
            'employee_id'     => $this->otherEmployee->id,
            'tanggal_mulai'   => '2026-02-01',
            'tanggal_selesai' => '2026-02-28',
            'status'          => 'aktif',
        ]);
    }

    // ── Helper: buat SpkEvaluation dengan nilai awal ──────────────────────────

    /**
     * Buat evaluasi awal dengan 1 absen tanpa keterangan.
     * C1 = 40, C2 = 100, C3 = 100 → nilai_akhir = 70.00, kategori = perhatian
     */
    private function buatEvaluasiAwal(): SpkEvaluation
    {
        $c1 = SpkCriteria::where('kode', 'C1')->first();
        $c2 = SpkCriteria::where('kode', 'C2')->first();
        $c3 = SpkCriteria::where('kode', 'C3')->first();

        // Absen tanpa keterangan → C1 penalti
        Attendance::create([
            'employee_id'        => $this->employee->id,
            'tanggal'            => '2026-02-03',
            'waktu_masuk'        => null,
            'waktu_keluar'       => null,
            'status'             => 'ABSENT',
            'metode'             => null,
            'menit_terlambat'    => 0,
            'absence_reason_id'  => null,   // tanpa keterangan
            'is_auto_generated'  => true,
        ]);

        // 1 hari hadir RFID tepat waktu (C2=100, C3=100)
        Attendance::create([
            'employee_id'        => $this->employee->id,
            'tanggal'            => '2026-02-04',
            'waktu_masuk'        => '06:55:00',
            'waktu_keluar'       => '17:00:00',
            'status'             => 'HADIR',
            'metode'             => 'RFID',
            'menit_terlambat'    => 0,
            'absence_reason_id'  => null,
            'is_auto_generated'  => false,
        ]);

        // Buat evaluasi "awal" secara manual (C1=40, C2=100, C3=100 → nilai_akhir=70.00)
        // C1n=0.4 * 0.5 = 0.2, C2n=1.0 * 0.35 = 0.35, C3n=1.0 * 0.15 = 0.15 → 0.7 * 100 = 70.00
        $evaluation = SpkEvaluation::create([
            'evaluation_period_id' => $this->period->id,
            'employee_id'          => $this->employee->id,
            'contract_id'          => $this->contract->id,
            'bobot_snapshot'       => ['C1' => 0.5, 'C2' => 0.35, 'C3' => 0.15],
            'nilai_akhir'          => 70.00,
            'kategori'             => 'perhatian',
            'calculated_at'        => now()->subDay(),
        ]);

        $evaluation->details()->create([
            'spk_criteria_id'   => $c1->id,
            'nilai_mentah'      => 40.0,
            'nilai_normalisasi' => 0.4,
            'bobot'             => 0.5,
            'kontribusi'        => 0.2,
            'jumlah_data_mentah' => [
                'absent_tanpa_keterangan'      => 1,
                'absent_sah'                   => 0,
                'daftar_tanggal_pemicu_penalti' => ['2026-02-03'],
            ],
        ]);
        $evaluation->details()->create([
            'spk_criteria_id'   => $c2->id,
            'nilai_mentah'      => 100.0,
            'nilai_normalisasi' => 1.0,
            'bobot'             => 0.35,
            'kontribusi'        => 0.35,
            'jumlah_data_mentah' => [
                'telat_1_10_menit'             => 0,
                'telat_lebih_10_menit'         => 0,
                'daftar_tanggal_pemicu_penalti' => [],
            ],
        ]);
        $evaluation->details()->create([
            'spk_criteria_id'   => $c3->id,
            'nilai_mentah'      => 100.0,
            'nilai_normalisasi' => 1.0,
            'bobot'             => 0.15,
            'kontribusi'        => 0.15,
            'jumlah_data_mentah' => [
                'total_hadir'                  => 1,
                'pakai_fingerprint'            => 0,
                'daftar_tanggal_pemicu_penalti' => [],
            ],
        ]);

        return $evaluation;
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    /**
     * Tanpa token harus 401.
     */
    public function test_recalculate_requires_auth(): void
    {
        $evaluation = $this->buatEvaluasiAwal();

        $response = $this->postJson("/api/evaluations/{$evaluation->id}/recalculate");
        $response->assertStatus(401);
    }

    /**
     * Evaluasi tidak ada harus 404.
     */
    public function test_recalculate_returns_404_for_missing_evaluation(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/evaluations/99999/recalculate');
        $response->assertStatus(404);
    }

    /**
     * SKENARIO UTAMA:
     * Setelah absence_reason_id diubah ke alasan sah → recalculate
     * C1 harus 100, nilai_akhir harus 100.00, kategori positif.
     * Objek 'sebelum' harus memuat nilai lama (70.00).
     * bukti_absensi harus memuat ABSEN_SAH dengan poin 0.
     */
    public function test_recalculate_after_reason_changed_to_sah(): void
    {
        $evaluation = $this->buatEvaluasiAwal();

        // Ubah absen tanpa keterangan → alasan sah
        Attendance::where('employee_id', $this->employee->id)
            ->where('tanggal', '2026-02-03')
            ->update(['absence_reason_id' => $this->alasanSah->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/evaluations/{$evaluation->id}/recalculate");

        $response->assertStatus(200);

        // Nilai akhir baru: C1=100, C2=100, C3=(1-0)/1*100=100 → 100.00
        $response->assertJsonPath('nilai_akhir', '100.00');
        $response->assertJsonPath('kategori', 'positif');

        // Nilai sebelum: 70.00
        $response->assertJsonPath('sebelum.nilai_akhir', '70.00');
        $response->assertJsonPath('sebelum.kategori', 'perhatian');

        // bukti_absensi harus ada ABSEN_SAH dengan poin 0
        $bukti = collect($response->json('bukti_absensi'));
        $absenSah = $bukti->firstWhere('jenis', 'ABSEN_SAH');
        $this->assertNotNull($absenSah, 'Harus ada entri ABSEN_SAH di bukti_absensi');
        $this->assertEquals(0, $absenSah['poin_penalti']);
        $this->assertEquals('C1', $absenSah['kriteria']);

        // Tidak boleh ada ABSEN_TANPA_KETERANGAN lagi
        $absenTS = $bukti->firstWhere('jenis', 'ABSEN_TANPA_KETERANGAN');
        $this->assertNull($absenTS, 'Tidak boleh ada ABSEN_TANPA_KETERANGAN setelah alasan diubah');
    }

    /**
     * ID evaluasi harus TIDAK berubah setelah recalculate.
     */
    public function test_recalculate_does_not_change_evaluation_id(): void
    {
        $evaluation = $this->buatEvaluasiAwal();
        $idLama     = $evaluation->id;

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/evaluations/{$idLama}/recalculate");

        $response->assertStatus(200);
        $response->assertJsonPath('id', $idLama);

        // Pastikan di database masih ada satu row dengan id yang sama
        $this->assertDatabaseHas('spk_evaluations', ['id' => $idLama]);
        $this->assertEquals(
            1,
            SpkEvaluation::where('evaluation_period_id', $this->period->id)
                ->where('employee_id', $this->employee->id)
                ->count()
        );
    }

    /**
     * Keputusan HRD yang sudah ada harus tetap ada setelah recalculate.
     */
    public function test_recalculate_preserves_hrd_decision(): void
    {
        $evaluation = $this->buatEvaluasiAwal();

        // Buat HRD decision
        HrdDecision::create([
            'spk_evaluation_id' => $evaluation->id,
            'decision'          => 'DIPERPANJANG',
            'catatan'           => 'Keputusan lama',
            'decided_by'        => $this->user->id,
            'decided_at'        => now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/evaluations/{$evaluation->id}/recalculate");

        $response->assertStatus(200);

        // HRD decision harus masih ada di database
        $this->assertDatabaseHas('hrd_decisions', [
            'spk_evaluation_id' => $evaluation->id,
            'decision'          => 'DIPERPANJANG',
            'catatan'           => 'Keputusan lama',
        ]);

        // Response keputusan_hrd tidak boleh null
        $response->assertJsonPath('keputusan_hrd.decision', 'DIPERPANJANG');
    }

    /**
     * Evaluasi karyawan LAIN tidak boleh tersentuh (calculated_at tidak berubah).
     */
    public function test_recalculate_does_not_touch_other_evaluations(): void
    {
        $evaluation = $this->buatEvaluasiAwal();

        $c1 = SpkCriteria::where('kode', 'C1')->first();
        $c2 = SpkCriteria::where('kode', 'C2')->first();
        $c3 = SpkCriteria::where('kode', 'C3')->first();

        $calculatedAtLama = now()->subHours(3);

        // Buat evaluasi untuk karyawan lain
        $otherEval = SpkEvaluation::create([
            'evaluation_period_id' => $this->period->id,
            'employee_id'          => $this->otherEmployee->id,
            'contract_id'          => $this->otherContract->id,
            'bobot_snapshot'       => ['C1' => 0.5, 'C2' => 0.35, 'C3' => 0.15],
            'nilai_akhir'          => 85.00,
            'kategori'             => 'pertimbangan',
            'calculated_at'        => $calculatedAtLama,
        ]);

        $otherEval->details()->create([
            'spk_criteria_id'    => $c1->id,
            'nilai_mentah'       => 100,
            'nilai_normalisasi'  => 1.0,
            'bobot'              => 0.5,
            'kontribusi'         => 0.5,
            'jumlah_data_mentah' => ['absent_tanpa_keterangan' => 0, 'absent_sah' => 0, 'daftar_tanggal_pemicu_penalti' => []],
        ]);
        $otherEval->details()->create([
            'spk_criteria_id'    => $c2->id,
            'nilai_mentah'       => 70,
            'nilai_normalisasi'  => 0.7,
            'bobot'              => 0.35,
            'kontribusi'         => 0.245,
            'jumlah_data_mentah' => ['telat_1_10_menit' => 3, 'telat_lebih_10_menit' => 0, 'daftar_tanggal_pemicu_penalti' => []],
        ]);
        $otherEval->details()->create([
            'spk_criteria_id'    => $c3->id,
            'nilai_mentah'       => 100,
            'nilai_normalisasi'  => 1.0,
            'bobot'              => 0.15,
            'kontribusi'         => 0.15,
            'jumlah_data_mentah' => ['total_hadir' => 5, 'pakai_fingerprint' => 0, 'daftar_tanggal_pemicu_penalti' => []],
        ]);

        // Recalculate karyawan pertama
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/evaluations/{$evaluation->id}/recalculate");

        $response->assertStatus(200);

        // calculated_at karyawan lain harus SAMA (tidak berubah)
        $otherEvalRefreshed = $otherEval->fresh();
        $this->assertEquals(
            $calculatedAtLama->toDateTimeString(),
            $otherEvalRefreshed->calculated_at->toDateTimeString(),
            'calculated_at evaluasi karyawan lain tidak boleh berubah'
        );
    }

    /**
     * Response harus mengandung semua field yang sama dengan GET /api/evaluations/{id}.
     */
    public function test_recalculate_response_has_full_show_structure(): void
    {
        $evaluation = $this->buatEvaluasiAwal();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/evaluations/{$evaluation->id}/recalculate");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'id',
            'employee',
            'nilai_akhir',
            'kategori',
            'rekomendasi',
            'calculated_at',
            'details',
            'langkah_saw' => [
                'langkah',
                'total_kontribusi',
                'nilai_akhir_persen',
                'kategori',
            ],
            'data_mentah' => [
                'absent_tanpa_keterangan',
                'absent_sah',
                'telat_1_10',
                'telat_lebih_10',
                'total_hadir',
                'pakai_fingerprint',
            ],
            'bukti_absensi',
            'keputusan_hrd',
            'sebelum' => [
                'nilai_akhir',
                'kategori',
                'calculated_at',
            ],
        ]);
    }

    /**
     * Angka pasti: 1 absen tanpa keterangan → C1=40, nilai_akhir=70.00.
     * Setelah reason diubah ke sah → C1=100, nilai_akhir=100.00.
     * Verifikasi detail SAW tersimpan di database dengan benar.
     */
    public function test_recalculate_updates_saw_details_in_database(): void
    {
        $evaluation = $this->buatEvaluasiAwal();

        // Ubah absen tanpa keterangan → alasan sah
        Attendance::where('employee_id', $this->employee->id)
            ->where('tanggal', '2026-02-03')
            ->update(['absence_reason_id' => $this->alasanSah->id]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/evaluations/{$evaluation->id}/recalculate")
            ->assertStatus(200);

        // Verifikasi nilai_akhir di database
        $this->assertDatabaseHas('spk_evaluations', [
            'id'          => $evaluation->id,
            'nilai_akhir' => 100.00,
            'kategori'    => 'positif',
        ]);

        // Verifikasi detail C1 di database: nilai_mentah harus 100
        $c1 = SpkCriteria::where('kode', 'C1')->first();
        $detailC1 = $evaluation->details()->where('spk_criteria_id', $c1->id)->first();
        $this->assertEquals(100.0, (float) $detailC1->nilai_mentah);
        $this->assertEquals(0.5,   (float) $detailC1->kontribusi);
    }
}
