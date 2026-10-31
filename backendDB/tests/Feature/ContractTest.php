<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_create_contract()
    {
        $employee = Employee::create([
            'nama' => 'John',
            'nip' => 'E1',
            'jabatan' => 'Staff',
            'status_aktif' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/contracts', [
            'employee_id' => $employee->id,
            'tanggal_mulai' => '2026-01-01',
            'tanggal_selesai' => '2026-12-31',
            'status' => 'aktif'
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('contracts', [
            'employee_id' => $employee->id,
            'tanggal_mulai' => '2026-01-01',
            'tanggal_selesai' => '2026-12-31',
            'status' => 'aktif'
        ]);
    }

    public function test_validates_tanggal_selesai_after_mulai()
    {
        $employee = Employee::create([
            'nama' => 'John',
            'nip' => 'E1',
            'jabatan' => 'Staff',
            'status_aktif' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/contracts', [
            'employee_id' => $employee->id,
            'tanggal_mulai' => '2026-06-01',
            'tanggal_selesai' => '2026-05-31', // Before mulai
            'status' => 'aktif'
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('tanggal_selesai');
    }

    public function test_rejects_overlapping_active_contracts()
    {
        $employee = Employee::create([
            'nama' => 'John',
            'nip' => 'E1',
            'jabatan' => 'Staff',
            'status_aktif' => true,
        ]);

        Contract::create([
            'employee_id' => $employee->id,
            'tanggal_mulai' => '2026-01-01',
            'tanggal_selesai' => '2026-12-31',
            'status' => 'aktif'
        ]);

        // Overlapping date
        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/contracts', [
            'employee_id' => $employee->id,
            'tanggal_mulai' => '2026-06-01',
            'tanggal_selesai' => '2027-05-31',
            'status' => 'aktif'
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Terdapat kontrak aktif yang tumpang tindih untuk karyawan ini.']);
    }

    public function test_allows_overlapping_if_status_not_active()
    {
        $employee = Employee::create([
            'nama' => 'John',
            'nip' => 'E1',
            'jabatan' => 'Staff',
            'status_aktif' => true,
        ]);

        Contract::create([
            'employee_id' => $employee->id,
            'tanggal_mulai' => '2026-01-01',
            'tanggal_selesai' => '2026-12-31',
            'status' => 'aktif'
        ]);

        // Same dates, but status is 'selesai' -> Should not trigger overlap block
        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/contracts', [
            'employee_id' => $employee->id,
            'tanggal_mulai' => '2026-01-01',
            'tanggal_selesai' => '2026-12-31',
            'status' => 'selesai'
        ]);

        $response->assertStatus(201);
    }
}
