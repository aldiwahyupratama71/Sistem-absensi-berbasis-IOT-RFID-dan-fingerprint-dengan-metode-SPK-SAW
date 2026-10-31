<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Fingerprint;
use App\Models\IotDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeFingerprintTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $employee;
    protected $device;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        
        $this->employee = Employee::create([
            'nip' => '123456',
            'nama' => 'John Doe',
            'departemen' => 'IT',
            'jabatan' => 'Staff',
            'status' => 'aktif',
            'status_aktif' => true,
        ]);
        
        $this->device = IotDevice::create([
            'nama' => 'ESP32 Fingerprint',
            'api_key_hash' => hash('sha256', 'dummy-key'),
            'lokasi' => 'Pintu Masuk',
        ]);
    }

    public function test_akses_tanpa_token_ditolak()
    {
        $response = $this->postJson("/api/employees/{$this->employee->id}/fingerprints", [
            'device_id' => $this->device->id,
            'finger_id' => '100',
        ]);

        $response->assertStatus(401);
    }

    public function test_tambah_fingerprint_berhasil()
    {
        $response = $this->actingAs($this->user)->postJson("/api/employees/{$this->employee->id}/fingerprints", [
            'device_id' => $this->device->id,
            'finger_id' => '100',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id', 'finger_id', 'iot_device_id', 'device_nama', 'created_at'
                ]
            ]);

        $this->assertDatabaseHas('fingerprints', [
            'employee_id' => $this->employee->id,
            'iot_device_id' => $this->device->id,
            'finger_id' => '100',
        ]);
    }

    public function test_finger_id_duplikat_di_device_sama_ditolak()
    {
        Fingerprint::create([
            'employee_id' => $this->employee->id,
            'iot_device_id' => $this->device->id,
            'finger_id' => '100',
        ]);

        $response = $this->actingAs($this->user)->postJson("/api/employees/{$this->employee->id}/fingerprints", [
            'device_id' => $this->device->id,
            'finger_id' => '100',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Finger ID sudah digunakan di device ini.',
            ]);
    }

    public function test_hapus_fingerprint_berhasil()
    {
        $fingerprint = Fingerprint::create([
            'employee_id' => $this->employee->id,
            'iot_device_id' => $this->device->id,
            'finger_id' => '100',
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/employees/{$this->employee->id}/fingerprints/{$fingerprint->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Data fingerprint berhasil dihapus dari sistem.',
            ]);

        $this->assertDatabaseMissing('fingerprints', [
            'id' => $fingerprint->id,
        ]);
    }
}
