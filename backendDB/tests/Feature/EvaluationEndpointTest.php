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

class EvaluationEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();

        $c1 = SpkCriteria::create(['kode' => 'C1', 'nama' => 'Kehadiran', 'tipe' => 'benefit', 'bobot' => 50.00]);
        $c2 = SpkCriteria::create(['kode' => 'C2', 'nama' => 'Ketepatan Waktu', 'tipe' => 'benefit', 'bobot' => 35.00]);
        $c3 = SpkCriteria::create(['kode' => 'C3', 'nama' => 'Kepatuhan ID Card', 'tipe' => 'benefit', 'bobot' => 15.00]);

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

        // Manually create an evaluation result
        $this->evaluation = SpkEvaluation::create([
            'evaluation_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'contract_id' => $this->contract->id,
            'bobot_snapshot' => ['C1' => 0.5, 'C2' => 0.35, 'C3' => 0.15],
            'nilai_akhir' => 95.50,
            'kategori' => 'positif',
        ]);

        $this->evaluation->details()->create([
            'spk_criteria_id' => $c1->id,
            'nilai_mentah' => 100,
            'nilai_normalisasi' => 1.0,
            'bobot' => 0.5,
            'kontribusi' => 0.5,
            'jumlah_data_mentah' => ['absent_tanpa_keterangan' => 0]
        ]);
        
        $this->evaluation->details()->create([
            'spk_criteria_id' => $c2->id,
            'nilai_mentah' => 100,
            'nilai_normalisasi' => 1.0,
            'bobot' => 0.35,
            'kontribusi' => 0.35,
            'jumlah_data_mentah' => ['telat_1_10_menit' => 0, 'telat_lebih_10_menit' => 0]
        ]);
        
        $this->evaluation->details()->create([
            'spk_criteria_id' => $c3->id,
            'nilai_mentah' => 70,
            'nilai_normalisasi' => 0.7,
            'bobot' => 0.15,
            'kontribusi' => 0.105,
            'jumlah_data_mentah' => ['total_hadir' => 10, 'pakai_fingerprint' => 3]
        ]);
    }

    public function test_calculate_endpoint_requires_auth()
    {
        $response = $this->postJson("/api/evaluation-periods/{$this->period->id}/calculate");
        $response->assertStatus(401);
    }

    public function test_calculate_endpoint_executes_service()
    {
        $response = $this->actingAs($this->user, 'sanctum')->postJson("/api/evaluation-periods/{$this->period->id}/calculate");
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'summary' => [
                'jumlah_karyawan',
                'distribusi_kategori',
                'warnings'
            ]
        ]);
    }

    public function test_results_list()
    {
        $response = $this->actingAs($this->user, 'sanctum')->getJson("/api/evaluation-periods/{$this->period->id}/results");
        
        $response->assertStatus(200);
        $response->assertJsonPath('data.0.nilai_akhir', '95.50');
        $response->assertJsonPath('data.0.nama', 'John');
        $response->assertJsonPath('data.0.kategori', 'positif');
    }

    public function test_evaluation_details()
    {
        $response = $this->actingAs($this->user, 'sanctum')->getJson("/api/evaluations/{$this->evaluation->id}");
        
        $response->assertStatus(200);
        $response->assertJsonPath('nilai_akhir', '95.50');
        $response->assertJsonPath('kategori', 'positif');
        $response->assertJsonPath('rekomendasi', 'Performa sangat baik. Direkomendasikan untuk apresiasi atau bonus.');
        
        // Assert formula strings
        $details = $response->json('details');
        $this->assertCount(3, $details);
        $c3 = collect($details)->firstWhere('kode', 'C3');
        $this->assertEquals('(10 - 3) / 10 * 100', $c3['rumus_substitusi']);
    }

    public function test_hrd_decision_valid()
    {
        $response = $this->actingAs($this->user, 'sanctum')->putJson("/api/evaluations/{$this->evaluation->id}/decision", [
            'decision' => 'DIPERPANJANG',
            'catatan' => 'Sangat bagus'
        ]);
        
        $response->assertStatus(200);
        $this->assertDatabaseHas('hrd_decisions', [
            'spk_evaluation_id' => $this->evaluation->id,
            'decision' => 'DIPERPANJANG',
            'catatan' => 'Sangat bagus',
            'decided_by' => $this->user->id
        ]);
    }

    public function test_hrd_decision_invalid()
    {
        $response = $this->actingAs($this->user, 'sanctum')->putJson("/api/evaluations/{$this->evaluation->id}/decision", [
            'catatan' => 'Tanpa decision'
        ]); // decision is required
        
        $response->assertStatus(422);
    }

    public function test_employee_evaluation_history()
    {
        $response = $this->actingAs($this->user, 'sanctum')->getJson("/api/employees/{$this->employee->id}/evaluation-history");
        
        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.nilai_akhir', '95.50');
    }

    public function test_criteria_list()
    {
        $response = $this->actingAs($this->user, 'sanctum')->getJson("/api/criteria");
        
        $response->assertStatus(200);
        $response->assertJsonCount(3);
        $response->assertJsonPath('0.kode', 'C1');
    }
}
