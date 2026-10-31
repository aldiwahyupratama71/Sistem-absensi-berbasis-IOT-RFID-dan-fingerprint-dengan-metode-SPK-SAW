<?php

namespace Tests\Feature\Middleware;

use App\Models\IotDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthenticateDeviceTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_bisa_mengakses_endpoint_dengan_api_key_valid()
    {
        $plainKey = Str::random(48);
        $device = IotDevice::factory()->create([
            'api_key_hash' => hash('sha256', $plainKey)
        ]);

        // Coba endpoint khusus device (link fingerprint dummy request)
        $response = $this->postJson("/api/devices/{$device->id}/fingerprints", [], [
            'X-API-KEY' => $plainKey
        ]);

        // Karena employee_id required di validation, harusnya return 422, bukan 401
        $response->assertStatus(422);
        
        // Memastikan last_seen_at terupdate
        $device->refresh();
        $this->assertNotNull($device->last_seen_at);
        $this->assertTrue($device->last_seen_at->isToday());
    }

    public function test_ditolak_jika_api_key_salah()
    {
        $device = IotDevice::factory()->create();

        $response = $this->postJson("/api/devices/{$device->id}/fingerprints", [], [
            'X-API-KEY' => 'key-salah'
        ]);

        $response->assertUnauthorized()
                 ->assertJsonPath('message', 'API key tidak valid.');
    }
    
    public function test_ditolak_jika_header_api_key_tidak_ada()
    {
        $device = IotDevice::factory()->create();

        $response = $this->postJson("/api/devices/{$device->id}/fingerprints");

        $response->assertUnauthorized()
                 ->assertJsonPath('message', 'API key device tidak ditemukan.');
    }
}
