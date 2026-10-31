<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeCard;
use App\Models\Fingerprint;
use App\Models\IotDevice;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AttendanceTapTest extends TestCase
{
    use RefreshDatabase;

    protected $device;
    protected $plainApiKey = 'test-api-key-123';
    protected $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->device = IotDevice::create([
            'nama' => 'ESP32-TEST',
            'api_key_hash' => hash('sha256', $this->plainApiKey),
            'lokasi' => 'Gate 1',
        ]);

        $this->employee = Employee::create([
            'nip' => '123456',
            'nama' => 'John Doe',
            'departemen' => 'IT',
            'jabatan' => 'Staff',
            'status' => 'aktif',
            'status_aktif' => true,
        ]);
    }

    public function test_rfid_tepat_waktu()
    {
        EmployeeCard::create([
            'employee_id' => $this->employee->id,
            'uid_rfid' => 'AA-BB-CC-DD',
            'status' => 'aktif',
        ]);

        $response = $this->withHeader('X-API-KEY', $this->plainApiKey)->postJson('/api/attendance/tap', [
            'device_id' => 'ESP32-TEST',
            'metode' => 'RFID',
            'identifier' => 'AA-BB-CC-DD',
            'timestamp' => '2026-09-30T06:55:00',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'nama' => 'John Doe',
            ]);

        $this->assertDatabaseHas('attendance', [
            'employee_id' => $this->employee->id,
            'status' => 'HADIR',
            'menit_terlambat' => 0,
            'metode' => 'RFID',
            'jam_masuk' => '06:55:00',
        ]);
    }

    public function test_rfid_telat()
    {
        EmployeeCard::create([
            'employee_id' => $this->employee->id,
            'uid_rfid' => 'AA-BB-CC-DD',
            'status' => 'aktif',
        ]);

        $response = $this->withHeader('X-API-KEY', $this->plainApiKey)->postJson('/api/attendance/tap', [
            'device_id' => 'ESP32-TEST',
            'metode' => 'RFID',
            'identifier' => 'AA-BB-CC-DD',
            'timestamp' => '2026-09-30T07:15:30', // 15 menit 30 detik telat
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('attendance', [
            'employee_id' => $this->employee->id,
            'status' => 'TERLAMBAT',
            'menit_terlambat' => 16, // 15:30 -> 16 mins
            'metode' => 'RFID',
            'jam_masuk' => '07:15:30',
        ]);
    }

    public function test_fingerprint_tepat_waktu()
    {
        Fingerprint::create([
            'employee_id' => $this->employee->id,
            'iot_device_id' => $this->device->id,
            'finger_id' => 1,
        ]);

        $response = $this->withHeader('X-API-KEY', $this->plainApiKey)->postJson('/api/attendance/tap', [
            'device_id' => 'ESP32-TEST',
            'metode' => 'FINGERPRINT',
            'identifier' => '1',
            'timestamp' => '2026-09-30T06:50:00',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('attendance', [
            'employee_id' => $this->employee->id,
            'status' => 'HADIR',
            'menit_terlambat' => 0,
            'metode' => 'FINGERPRINT',
        ]);
    }

    public function test_tap_ganda()
    {
        EmployeeCard::create([
            'employee_id' => $this->employee->id,
            'uid_rfid' => 'AA-BB-CC-DD',
            'status' => 'aktif',
        ]);

        Attendance::create([
            'employee_id' => $this->employee->id,
            'iot_device_id' => $this->device->id,
            'tanggal' => '2026-09-30',
            'jam_masuk' => '06:50:00',
            'metode' => 'RFID',
            'status' => 'HADIR',
            'menit_terlambat' => 0,
            'is_auto_generated' => false,
        ]);

        $response = $this->withHeader('X-API-KEY', $this->plainApiKey)->postJson('/api/attendance/tap', [
            'device_id' => 'ESP32-TEST',
            'metode' => 'RFID',
            'identifier' => 'AA-BB-CC-DD',
            'timestamp' => '2026-09-30T07:10:00',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'info' => 'sudah_absen',
            ]);

        // Pastikan tidak update jadi telat
        $this->assertDatabaseHas('attendance', [
            'employee_id' => $this->employee->id,
            'status' => 'HADIR',
            'jam_masuk' => '06:50:00',
        ]);
    }

    public function test_identifier_tidak_dikenal()
    {
        $response = $this->withHeader('X-API-KEY', $this->plainApiKey)->postJson('/api/attendance/tap', [
            'device_id' => 'ESP32-TEST',
            'metode' => 'RFID',
            'identifier' => 'UNKNOWN-RFID',
            'timestamp' => '2026-09-30T06:55:00',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'not_found',
            ]);
    }

    public function test_api_key_salah()
    {
        $response = $this->withHeader('X-API-KEY', 'wrong-key')->postJson('/api/attendance/tap', [
            'device_id' => 'ESP32-TEST',
            'metode' => 'RFID',
            'identifier' => 'AA-BB-CC-DD',
            'timestamp' => '2026-09-30T06:55:00',
        ]);

        $response->assertStatus(401);
    }

    public function test_tap_setelah_baris_absent_otomatis()
    {
        EmployeeCard::create([
            'employee_id' => $this->employee->id,
            'uid_rfid' => 'AA-BB-CC-DD',
            'status' => 'aktif',
        ]);

        Attendance::create([
            'employee_id' => $this->employee->id,
            'tanggal' => '2026-09-30',
            'status' => 'ABSENT',
            'is_auto_generated' => true,
        ]);

        $response = $this->withHeader('X-API-KEY', $this->plainApiKey)->postJson('/api/attendance/tap', [
            'device_id' => 'ESP32-TEST',
            'metode' => 'RFID',
            'identifier' => 'AA-BB-CC-DD',
            'timestamp' => '2026-09-30T07:05:00',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('attendance', [
            'employee_id' => $this->employee->id,
            'status' => 'TERLAMBAT',
            'is_auto_generated' => false,
            'jam_masuk' => '07:05:00',
        ]);
        
        $this->assertDatabaseMissing('attendance', [
            'employee_id' => $this->employee->id,
            'status' => 'ABSENT',
        ]);
    }

    public function test_tap_tanpa_device_id()
    {
        EmployeeCard::create([
            'employee_id' => $this->employee->id,
            'uid_rfid' => 'AA-BB-CC-DD',
            'status' => 'aktif',
        ]);

        // Request tanpa field device_id
        $response = $this->withHeader('X-API-KEY', $this->plainApiKey)->postJson('/api/attendance/tap', [
            'metode' => 'RFID',
            'identifier' => 'AA-BB-CC-DD',
            'timestamp' => '2026-09-30T06:55:00',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'nama' => 'John Doe',
            ]);

        $this->assertDatabaseHas('attendance', [
            'employee_id' => $this->employee->id,
            'status' => 'HADIR',
        ]);
    }
}
