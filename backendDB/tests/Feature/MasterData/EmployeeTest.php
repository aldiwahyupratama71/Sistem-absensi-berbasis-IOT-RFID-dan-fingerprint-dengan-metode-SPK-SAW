<?php

namespace Tests\Feature\MasterData;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Otomatis login sebagai HRD
        $user = User::factory()->create(['role' => 'hrd']);
        Sanctum::actingAs($user, ['*']);
    }

    public function test_index_mengembalikan_daftar_karyawan_dan_paginate()
    {
        Employee::factory()->count(20)->create();

        $response = $this->getJson('/api/employees');

        $response->assertOk()
                 ->assertJsonCount(15, 'data') // default paginate 15
                 ->assertJsonStructure([
                     'data' => [
                         '*' => ['id', 'nip', 'nama', 'departemen', 'status', 'status_aktif']
                     ],
                     'meta' => ['current_page', 'last_page', 'total']
                 ]);
    }

    public function test_index_bisa_pencarian_nama_dan_nip()
    {
        Employee::factory()->create(['nama' => 'Budi Santoso', 'nip' => 'NIP123']);
        Employee::factory()->create(['nama' => 'Andi', 'nip' => 'NIP456']);

        // Cari berdasarkan nama
        $response = $this->getJson('/api/employees?search=budi');
        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals('Budi Santoso', $response->json('data.0.nama'));

        // Cari berdasarkan nip
        $response2 = $this->getJson('/api/employees?search=NIP456');
        $response2->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals('Andi', $response2->json('data.0.nama'));
    }

    public function test_store_berhasil_menambahkan_karyawan()
    {
        $response = $this->postJson('/api/employees', [
            'nip'        => 'NIP001',
            'nama'       => 'Siti',
            'departemen' => 'IT',
            'jabatan'    => 'Programmer',
        ]);

        $response->assertCreated()
                 ->assertJsonPath('data.nip', 'NIP001')
                 ->assertJsonPath('data.status', 'aktif')
                 ->assertJsonPath('data.status_aktif', true);

        $this->assertDatabaseHas('employees', ['nip' => 'NIP001', 'status_aktif' => true]);
    }

    public function test_store_gagal_jika_nip_duplikat()
    {
        Employee::factory()->create(['nip' => 'NIP001']);

        $response = $this->postJson('/api/employees', [
            'nip'        => 'NIP001',
            'nama'       => 'Dudi',
        ]);

        $response->assertUnprocessable()
                 ->assertJsonValidationErrors(['nip']);
    }

    public function test_update_berhasil()
    {
        $employee = Employee::factory()->create(['nip' => 'NIP001', 'nama' => 'Lama']);

        $response = $this->putJson("/api/employees/{$employee->id}", [
            'nama' => 'Baru',
        ]);

        $response->assertOk()
                 ->assertJsonPath('data.nama', 'Baru');

        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'nama' => 'Baru']);
    }
    
    public function test_update_mengabaikan_unique_nip_milik_sendiri()
    {
        $employee = Employee::factory()->create(['nip' => 'NIP001']);

        $response = $this->putJson("/api/employees/{$employee->id}", [
            'nip' => 'NIP001', // tidak diubah
            'nama' => 'Nama Update'
        ]);

        $response->assertOk();
    }

    public function test_destroy_hapus_permanen_karyawan_tanpa_riwayat()
    {
        // Karyawan baru tanpa attendance/contracts/evaluasi: harus berhasil dihapus
        $employee = Employee::factory()->create();

        $response = $this->deleteJson("/api/employees/{$employee->id}");

        $response->assertOk()
                 ->assertJsonPath('message', 'Karyawan berhasil dihapus secara permanen.');

        $this->assertDatabaseMissing('employees', ['id' => $employee->id]);
    }

    public function test_destroy_ditolak_jika_kartu_masih_aktif()
    {
        // Karyawan tanpa riwayat attendance/contracts/evaluasi, tapi masih punya kartu aktif
        $employee = Employee::factory()->create();
        $employee->cards()->create(['uid_rfid' => 'AABB11', 'status' => 'aktif']);

        $response = $this->deleteJson("/api/employees/{$employee->id}");

        $response->assertStatus(422)
                 ->assertJsonPath('message', 'Nonaktifkan semua kartu RFID karyawan ini terlebih dahulu sebelum menghapus.');

        // Karyawan dan kartu harus tetap utuh
        $this->assertDatabaseHas('employees', ['id' => $employee->id]);
        $this->assertDatabaseHas('employee_cards', ['uid_rfid' => 'AABB11']);
    }
    
    public function test_tambah_kartu_rfid_berhasil()
    {
        $employee = Employee::factory()->create();

        $response = $this->postJson("/api/employees/{$employee->id}/cards", [
            'uid_rfid' => 'UID-1234'
        ]);

        $response->assertCreated()
                 ->assertJsonPath('data.uid_rfid', 'UID-1234')
                 ->assertJsonPath('data.status', 'aktif');
                 
        $this->assertDatabaseHas('employee_cards', ['uid_rfid' => 'UID-1234', 'employee_id' => $employee->id]);
    }
    
    public function test_tambah_kartu_rfid_gagal_jika_karyawan_nonaktif()
    {
        $employee = Employee::factory()->nonaktif()->create();

        $response = $this->postJson("/api/employees/{$employee->id}/cards", [
            'uid_rfid' => 'UID-1234'
        ]);

        $response->assertStatus(422);
    }
    
    public function test_nonaktifkan_kartu_rfid()
    {
        $employee = Employee::factory()->create();
        $card = $employee->cards()->create(['uid_rfid' => 'UID-11', 'status' => 'aktif']);
        
        $response = $this->patchJson("/api/employees/{$employee->id}/cards/{$card->id}/status");
        
        $response->assertOk()
                 ->assertJsonPath('data.status', 'nonaktif');
                 
        $this->assertDatabaseHas('employee_cards', ['id' => $card->id, 'status' => 'nonaktif']);
    }

    public function test_get_cards_mengembalikan_daftar_kartu()
    {
        $employee = Employee::factory()->create();
        $employee->cards()->create(['uid_rfid' => 'UID-1', 'status' => 'aktif']);
        $employee->cards()->create(['uid_rfid' => 'UID-2', 'status' => 'nonaktif']);

        $response = $this->getJson("/api/employees/{$employee->id}/cards");

        $response->assertOk()
                 ->assertJsonCount(2, 'data')
                 ->assertJsonStructure([
                     'data' => [
                         '*' => ['id', 'uid_rfid', 'status', 'created_at']
                     ]
                 ]);
    }

    public function test_get_cards_karyawan_tidak_ditemukan_mengembalikan_404()
    {
        $response = $this->getJson('/api/employees/9999/cards');
        $response->assertNotFound();
    }

    public function test_destroy_ditolak_jika_punya_riwayat_dan_data_tetap_utuh()
    {
        // Karyawan dengan riwayat attendance: hapus harus ditolak dan data tidak boleh berubah
        $employee = Employee::factory()->create();
        $employee->attendance()->create([
            'tanggal'     => '2026-09-01',
            'status'      => 'HADIR',
            'jam_masuk'   => '07:00:00',
            'menit_terlambat' => 0,
        ]);
        // Juga buat kartu nonaktif dan fingerprint — harus tetap ada setelah ditolak
        $card = $employee->cards()->create(['uid_rfid' => 'CC1122', 'status' => 'nonaktif']);

        $response = $this->deleteJson("/api/employees/{$employee->id}");

        $response->assertStatus(422)
                 ->assertJsonPath('message', 'Karyawan memiliki riwayat absensi/kontrak/evaluasi dan tidak bisa dihapus permanen. Nonaktifkan lewat menu Edit.');

        // Karyawan, kartu, dan attendance harus tetap utuh
        $this->assertDatabaseHas('employees', ['id' => $employee->id]);
        $this->assertDatabaseHas('employee_cards', ['id' => $card->id]);
        $this->assertDatabaseHas('attendance', ['employee_id' => $employee->id]);
    }

    public function test_nonaktifkan_karyawan_via_update_berhasil()
    {
        $employee = Employee::factory()->create(['status' => 'aktif', 'status_aktif' => true]);

        $response = $this->putJson("/api/employees/{$employee->id}", [
            'status_aktif' => false,
        ]);

        $response->assertOk()
                 ->assertJsonPath('data.status_aktif', false)
                 ->assertJsonPath('data.status', 'nonaktif');

        $this->assertDatabaseHas('employees', [
            'id'          => $employee->id,
            'status'      => 'nonaktif',
            'status_aktif' => false,
        ]);
    }

    public function test_aktifkan_kembali_karyawan_via_update_berhasil()
    {
        $employee = Employee::factory()->create(['status' => 'nonaktif', 'status_aktif' => false]);

        $response = $this->putJson("/api/employees/{$employee->id}", [
            'status_aktif' => true,
        ]);

        $response->assertOk()
                 ->assertJsonPath('data.status_aktif', true)
                 ->assertJsonPath('data.status', 'aktif');

        $this->assertDatabaseHas('employees', [
            'id'          => $employee->id,
            'status'      => 'aktif',
            'status_aktif' => true,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_force_purge_menghapus_semua_data_karyawan()
    {
        // Buat karyawan dengan riwayat absensi, kartu, dan fingerprint
        $employee = Employee::factory()->create();
        $employee->attendance()->create([
            'tanggal'         => '2026-09-01',
            'status'          => 'HADIR',
            'jam_masuk'       => '07:00:00',
            'menit_terlambat' => 0,
        ]);
        $card = $employee->cards()->create(['uid_rfid' => 'PURGE-UID', 'status' => 'aktif']);

        // Tanpa force → harus ditolak 422
        $this->deleteJson("/api/employees/{$employee->id}")->assertStatus(422);

        // Dengan force=true → harus berhasil
        $response = $this->deleteJson("/api/employees/{$employee->id}?force=true");

        $response->assertOk()
                 ->assertJsonPath('message', 'Semua data karyawan berhasil dihapus secara permanen.')
                 ->assertJsonStructure(['message', 'deleted' => ['absensi', 'kontrak', 'evaluasi', 'kartu_rfid', 'sidik_jari']]);

        // Semua data harus benar-benar hilang
        $this->assertDatabaseMissing('employees',      ['id' => $employee->id]);
        $this->assertDatabaseMissing('attendance',     ['employee_id' => $employee->id]);
        $this->assertDatabaseMissing('employee_cards', ['id' => $card->id]);
    }
}
