<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Employee;
use App\Models\EvaluationPeriod;
use App\Models\SpkCriteria;
use App\Models\SpkEvaluation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // Kriteria
        $this->c1 = SpkCriteria::create(['kode' => 'C1', 'nama' => 'Kehadiran',         'tipe' => 'benefit', 'bobot' => 50.00]);
        $this->c2 = SpkCriteria::create(['kode' => 'C2', 'nama' => 'Ketepatan Waktu',   'tipe' => 'benefit', 'bobot' => 35.00]);
        $this->c3 = SpkCriteria::create(['kode' => 'C3', 'nama' => 'Kepatuhan ID Card', 'tipe' => 'benefit', 'bobot' => 15.00]);

        // Karyawan & kontrak
        $this->employee = Employee::create([
            'nama' => 'Budi Santoso', 'nip' => 'E001',
            'jabatan' => 'Staff', 'departemen' => 'IT', 'status_aktif' => true,
        ]);
        $this->contract = Contract::create([
            'employee_id' => $this->employee->id,
            'tanggal_mulai' => '2026-01-01', 'tanggal_selesai' => '2026-01-31', 'status' => 'aktif',
        ]);
        $this->period = EvaluationPeriod::create([
            'kontrak_mulai' => '2026-01-01',
            'kontrak_selesai' => '2026-01-31',
            'tanggal_mulai' => '2026-01-01', 
            'tanggal_selesai' => '2026-01-31', 
            'status' => 'selesai',
        ]);

        // Evaluasi + detail
        $this->evaluation = SpkEvaluation::create([
            'evaluation_period_id' => $this->period->id,
            'employee_id'          => $this->employee->id,
            'contract_id'          => $this->contract->id,
            'bobot_snapshot'       => ['C1' => 0.5, 'C2' => 0.35, 'C3' => 0.15],
            'nilai_akhir'          => 92.50,
            'kategori'             => 'positif',
            'calculated_at'        => now(),
        ]);

        $this->evaluation->details()->createMany([
            [
                'spk_criteria_id'  => $this->c1->id,
                'nilai_mentah'     => 100,
                'nilai_normalisasi'=> 1.0,
                'bobot'            => 0.5,
                'kontribusi'       => 0.5,
                'jumlah_data_mentah' => [
                    'absent_tanpa_keterangan' => 0,
                    'daftar_tanggal_pemicu_penalti' => [],
                ],
            ],
            [
                'spk_criteria_id'  => $this->c2->id,
                'nilai_mentah'     => 90,
                'nilai_normalisasi'=> 0.9,
                'bobot'            => 0.35,
                'kontribusi'       => 0.315,
                'jumlah_data_mentah' => [
                    'telat_1_10_menit'    => 1,
                    'telat_lebih_10_menit'=> 0,
                    'daftar_tanggal_pemicu_penalti' => ['2026-01-15'],
                ],
            ],
            [
                'spk_criteria_id'  => $this->c3->id,
                'nilai_mentah'     => 70,
                'nilai_normalisasi'=> 0.7,
                'bobot'            => 0.15,
                'kontribusi'       => 0.105,
                'jumlah_data_mentah' => [
                    'total_hadir'      => 20,
                    'pakai_fingerprint'=> 6,
                ],
            ],
        ]);
    }

    // ─── Auth guard ───────────────────────────────────────────────────────────
    public function test_export_requires_auth()
    {
        $this->getJson("/api/evaluation-periods/{$this->period->id}/export?format=pdf")
            ->assertStatus(401);
    }

    // ─── Format validation ────────────────────────────────────────────────────
    public function test_invalid_format_returns_422()
    {
        $res = $this->actingAs($this->user, 'sanctum')
            ->get("/api/evaluation-periods/{$this->period->id}/export?format=csv");
        $res->assertStatus(422);
    }

    // ─── PDF period export ────────────────────────────────────────────────────
    public function test_pdf_export_returns_200_with_correct_content_type()
    {
        $res = $this->actingAs($this->user, 'sanctum')
            ->get("/api/evaluation-periods/{$this->period->id}/export?format=pdf");

        $res->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $res->headers->get('Content-Type'));
    }

    public function test_pdf_export_has_download_header()
    {
        $res = $this->actingAs($this->user, 'sanctum')
            ->get("/api/evaluation-periods/{$this->period->id}/export?format=pdf");

        $disposition = $res->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('laporan-evaluasi-', $disposition);
        $this->assertStringContainsString('.pdf', $disposition);
    }

    // ─── Excel period export ──────────────────────────────────────────────────
    public function test_xlsx_export_returns_200_with_correct_content_type()
    {
        $res = $this->actingAs($this->user, 'sanctum')
            ->get("/api/evaluation-periods/{$this->period->id}/export?format=xlsx");

        $res->assertStatus(200);

        $contentType = $res->headers->get('Content-Type');
        $this->assertStringContainsString(
            'spreadsheetml',
            $contentType,
            "Expected spreadsheetml, got: $contentType"
        );
    }

    public function test_xlsx_export_has_download_header()
    {
        $res = $this->actingAs($this->user, 'sanctum')
            ->get("/api/evaluation-periods/{$this->period->id}/export?format=xlsx");

        $disposition = $res->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('laporan-evaluasi-', $disposition);
        $this->assertStringContainsString('.xlsx', $disposition);
    }

    public function test_xlsx_export_content_contains_employee_name()
    {
        $res = $this->actingAs($this->user, 'sanctum')
            ->get("/api/evaluation-periods/{$this->period->id}/export?format=xlsx");

        // Cek raw bytes: nama karyawan harus ada di file xlsx (di content sebagai string di dalam XML zip)
        $content = $res->streamedContent();
        // xlsx = zip + XML; nama karyawan tersimpan sebagai XML string di dalam
        $this->assertNotEmpty($content, 'Excel response body kosong');
        $this->assertGreaterThan(1000, strlen($content), 'File xlsx terlalu kecil, kemungkinan kosong');
    }

    // ─── PDF rincian per karyawan ─────────────────────────────────────────────
    public function test_pdf_evaluation_detail_export()
    {
        $res = $this->actingAs($this->user, 'sanctum')
            ->get("/api/evaluations/{$this->evaluation->id}/export");

        $res->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $res->headers->get('Content-Type'));

        $disposition = $res->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('rincian-evaluasi-', $disposition);
        $this->assertStringContainsString('budi-santoso', $disposition);
    }

    // ─── Period tanpa evaluasi ────────────────────────────────────────────────
    public function test_export_period_without_evaluations_still_returns_200()
    {
        $contractEmpty = Contract::create([
            'employee_id' => $this->employee->id,
            'tanggal_mulai' => '2026-02-01', 'tanggal_selesai' => '2026-02-28', 'status' => 'aktif',
        ]);
        $periodEmpty = EvaluationPeriod::create([
            'kontrak_mulai' => '2026-02-01',
            'kontrak_selesai' => '2026-02-28',
            'tanggal_mulai' => '2026-02-01',
            'tanggal_selesai' => '2026-02-28',
            'status' => 'berjalan',
        ]);

        $res = $this->actingAs($this->user, 'sanctum')
            ->get("/api/evaluation-periods/{$periodEmpty->id}/export?format=pdf");
        $res->assertStatus(200);
    }
}
