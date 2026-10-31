<?php

namespace Tests\Feature;

use App\Models\IotDevice;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeviceHeartbeatTest extends TestCase
{
    use RefreshDatabase;

    public function test_heartbeat_dengan_api_key_valid_berhasil()
    {
        $plainKey = Str::random(48);
        $device = IotDevice::create([
            'nama' => 'Test Device',
            'api_key_hash' => hash('sha256', $plainKey),
            'api_key_prefix' => substr($plainKey, 0, 8),
            'last_seen_at' => null,
        ]);

        $response = $this->postJson('/api/devices/heartbeat', [], [
            'X-API-KEY' => $plainKey,
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);

        $this->assertNotNull($device->fresh()->last_seen_at);
    }

    public function test_heartbeat_dengan_api_key_salah_ditolak()
    {
        $plainKey = Str::random(48);
        $device = IotDevice::create([
            'nama' => 'Test Device',
            'api_key_hash' => hash('sha256', $plainKey),
            'api_key_prefix' => substr($plainKey, 0, 8),
            'last_seen_at' => null,
        ]);

        $response = $this->postJson('/api/devices/heartbeat', [], [
            'X-API-KEY' => 'SALAH12345',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'API key tidak valid.']);

        $this->assertNull($device->fresh()->last_seen_at);
    }

    public function test_device_berubah_status_koneksi_setelah_heartbeat()
    {
        Carbon::setTestNow('2026-10-01 10:00:00');
        
        $plainKey = Str::random(48);
        $device = IotDevice::create([
            'nama' => 'Test Device',
            'api_key_hash' => hash('sha256', $plainKey),
            'api_key_prefix' => substr($plainKey, 0, 8),
            'last_seen_at' => null, // Awalnya offline
        ]);

        // Cek status via resource 
        $resource = new \App\Http\Resources\IotDeviceResource($device);
        $this->assertEquals('offline', $resource->toArray(request())['status_koneksi']);

        // Heartbeat
        $this->postJson('/api/devices/heartbeat', [], [
            'X-API-KEY' => $plainKey,
        ]);

        // Setelah heartbeat harusnya online
        $device->refresh();
        $resource = new \App\Http\Resources\IotDeviceResource($device);
        $this->assertEquals('online', $resource->toArray(request())['status_koneksi']);

        // Simulasi waktu lewat threshold (default 90 detik)
        Carbon::setTestNow('2026-10-01 10:01:31'); // Lewat 91 detik
        
        // Status kembali offline
        $resource = new \App\Http\Resources\IotDeviceResource($device);
        $this->assertEquals('offline', $resource->toArray(request())['status_koneksi']);
    }
}
