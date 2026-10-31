<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Employee;
use App\Models\EvaluationPeriod;
use App\Models\SpkEvaluation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluationPeriodTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();

        $this->employee = Employee::create([
            'nama' => 'John',
            'nip' => 'E1',
            'jabatan' => 'Staff',
            'status_aktif' => true,
        ]);

        $this->contract = Contract::create([
            'employee_id' => $this->employee->id,
            'tanggal_mulai' => '2026-01-01',
            'tanggal_selesai' => '2026-12-31',
            'status' => 'aktif'
        ]);
    }

    public function test_can_create_evaluation_period()
    {
        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/evaluation-periods', [
            'kontrak_mulai'   => '2026-01-01',
            'kontrak_selesai' => '2026-12-31',
            'tanggal_mulai'   => '2026-01-01',
            'tanggal_selesai' => '2026-06-30',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('evaluation_periods', [
            'kontrak_mulai'   => '2026-01-01',
            'kontrak_selesai' => '2026-12-31',
            'tanggal_mulai'   => '2026-01-01',
            'tanggal_selesai' => '2026-06-30',
            'status'          => 'berjalan',
        ]);
    }

    public function test_rejects_if_contract_group_not_found()
    {
        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/evaluation-periods', [
            'kontrak_mulai'   => '2027-01-01',
            'kontrak_selesai' => '2027-12-31',
            'tanggal_mulai'   => '2027-01-01',
            'tanggal_selesai' => '2027-06-30',
        ]);

        $response->assertStatus(422);
    }

    public function test_status_defaults_to_berjalan_without_field_in_request()
    {
        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/evaluation-periods', [
            'kontrak_mulai'   => '2026-01-01',
            'kontrak_selesai' => '2026-12-31',
            'tanggal_mulai'   => '2026-07-01',
            'tanggal_selesai' => '2026-12-31',
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['status' => 'berjalan']);
    }

    public function test_update_can_change_status_to_selesai()
    {
        $period = EvaluationPeriod::create([
            'kontrak_mulai'   => '2026-01-01',
            'kontrak_selesai' => '2026-12-31',
            'tanggal_mulai'   => '2026-01-01',
            'tanggal_selesai' => '2026-06-30',
            'status'          => 'berjalan',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->patchJson("/api/evaluation-periods/{$period->id}", [
            'status' => 'selesai',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('evaluation_periods', [
            'id'     => $period->id,
            'status' => 'selesai',
        ]);
    }

    public function test_validates_period_must_be_within_contract()
    {
        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/evaluation-periods', [
            'kontrak_mulai' => '2026-01-01',
            'kontrak_selesai' => '2026-12-31',
            'tanggal_mulai' => '2025-12-01', // Before contract starts
            'tanggal_selesai' => '2026-06-30',
        ]);

        $response->assertStatus(422);
    }

    public function test_rejects_overlapping_periods_in_same_group()
    {
        EvaluationPeriod::create([
            'kontrak_mulai' => '2026-01-01',
            'kontrak_selesai' => '2026-12-31',
            'tanggal_mulai' => '2026-01-01',
            'tanggal_selesai' => '2026-06-30',
            'status' => 'selesai'
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/evaluation-periods', [
            'kontrak_mulai' => '2026-01-01',
            'kontrak_selesai' => '2026-12-31',
            'tanggal_mulai' => '2026-06-01',
            'tanggal_selesai' => '2026-12-31',
        ]);

        $response->assertStatus(422);
    }

    public function test_rejects_update_or_delete_if_has_evaluation_result_unless_forced()
    {
        $period = EvaluationPeriod::create([
            'kontrak_mulai' => '2026-01-01',
            'kontrak_selesai' => '2026-12-31',
            'tanggal_mulai' => '2026-01-01',
            'tanggal_selesai' => '2026-06-30',
            'status' => 'selesai'
        ]);

        SpkEvaluation::create([
            'evaluation_period_id' => $period->id,
            'employee_id' => $this->employee->id,
            'contract_id' => $this->contract->id,
            'bobot_snapshot' => ['C1' => 0.5],
            'nilai_akhir' => 80.50,
            'kategori' => 'Positif',
            'calculated_at' => now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->patchJson("/api/evaluation-periods/{$period->id}", [
            'status' => 'berjalan'
        ]);
        $response->assertStatus(422);

        $response = $this->actingAs($this->user, 'sanctum')->deleteJson("/api/evaluation-periods/{$period->id}");
        $response->assertStatus(422);

        $response = $this->actingAs($this->user, 'sanctum')->patchJson("/api/evaluation-periods/{$period->id}?force=true", [
            'status' => 'berjalan'
        ]);
        $response->assertStatus(200);

        $response = $this->actingAs($this->user, 'sanctum')->deleteJson("/api/evaluation-periods/{$period->id}?force=true");
        $response->assertStatus(204);
    }

    public function test_dates_are_saved_and_returned_without_timezone_shift()
    {
        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/evaluation-periods', [
            'kontrak_mulai'   => '2026-01-01',
            'kontrak_selesai' => '2026-12-31',
            'tanggal_mulai'   => '2026-09-01',
            'tanggal_selesai' => '2026-09-30',
        ]);
        
        // Cek bahwa data yang dikembalikan berformat Y-m-d dan tidak berubah jadi ISO string (T...Z)
        $response->assertStatus(201);
        $response->assertJsonFragment([
            'kontrak_mulai' => '2026-01-01',
            'kontrak_selesai' => '2026-12-31',
            'tanggal_mulai' => '2026-09-01',
            'tanggal_selesai' => '2026-09-30',
        ]);
    }
}
