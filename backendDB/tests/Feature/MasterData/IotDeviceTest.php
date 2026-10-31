<?php

namespace Tests\Feature\MasterData;

use App\Models\IotDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IotDeviceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Login as HRD
        $user = User::factory()->create(['role' => 'hrd']);
        Sanctum::actingAs($user, ['*']);
    }

    public function test_index_mengembalikan_daftar_device()
    {
        IotDevice::factory()->count(3)->create();

        $response = $this->getJson('/api/devices');

        $response->assertOk()
                 ->assertJsonCount(3, 'data')
                 ->assertJsonStructure([
                     'data' => [
                         '*' => ['id', 'nama', 'lokasi', 'api_key_prefix', 'status_koneksi']
                     ]
                 ]);
                 
        // Pastikan api_key dan hash tidak ada di list
        $response->assertJsonMissingPath('data.0.api_key_hash');
        $response->assertJsonMissingPath('data.0.api_key');
    }

    public function test_store_device_berhasil_dan_mengembalikan_plaintext_key()
    {
        $response = $this->postJson('/api/devices', [
            'nama'   => 'Pintu Utama',
            'lokasi' => 'Lantai 1',
        ]);

        $response->assertCreated()
                 ->assertJsonPath('data.nama', 'Pintu Utama')
                 ->assertJsonPath('data.lokasi', 'Lantai 1')
                 ->assertJsonStructure([
                     'data' => ['id', 'nama', 'lokasi'],
                     'api_key' // Plaintext key returned ONCE
                 ]);
                 
        $apiKey = $response->json('api_key');
        $this->assertNotNull($apiKey);
        $this->assertTrue(strlen($apiKey) >= 40);

        // Pastikan tersimpan hashed
        $device = IotDevice::first();
        $this->assertEquals(hash('sha256', $apiKey), $device->api_key_hash);
        $this->assertEquals(substr($apiKey, 0, 8), $device->api_key_prefix);
    }
    
    public function test_show_device_menghitung_status_koneksi_online()
    {
        $device = IotDevice::factory()->online()->create();
        
        $response = $this->getJson("/api/devices/{$device->id}");
        
        $response->assertOk()
                 ->assertJsonPath('data.status_koneksi', 'online');
    }
    
    public function test_show_device_menghitung_status_koneksi_offline()
    {
        $device = IotDevice::factory()->create(['last_seen_at' => now()->subMinutes(10)]);
        
        $response = $this->getJson("/api/devices/{$device->id}");
        
        $response->assertOk()
                 ->assertJsonPath('data.status_koneksi', 'offline');
    }

    public function test_regenerate_key_berhasil()
    {
        $device = IotDevice::factory()->create();
        $oldHash = $device->api_key_hash;

        $response = $this->postJson("/api/devices/{$device->id}/regenerate-key");

        $response->assertOk()
                 ->assertJsonStructure([
                     'data',
                     'api_key' // Plaintext key baru
                 ]);

        $newKey = $response->json('api_key');
        $device->refresh();
        
        $this->assertNotEquals($oldHash, $device->api_key_hash);
        $this->assertEquals(hash('sha256', $newKey), $device->api_key_hash);
    }
    
    public function test_destroy_menghapus_device_permanent()
    {
        $device = IotDevice::factory()->create();
        
        $this->deleteJson("/api/devices/{$device->id}")->assertOk();
        
        $this->assertDatabaseMissing('iot_devices', ['id' => $device->id]);
    }
}
