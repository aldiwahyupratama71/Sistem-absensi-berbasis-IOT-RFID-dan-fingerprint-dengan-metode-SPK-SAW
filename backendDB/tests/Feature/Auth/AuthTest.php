<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'email'    => 'hrd@spk-absensi.local',
            'password' => bcrypt('secret123'),
            'role'     => 'hrd',
        ], $attrs));
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Bersihkan rate limiter sebelum setiap test agar tidak saling pengaruh
        RateLimiter::clear('hrd@spk-absensi.local|127.0.0.1');
    }

    // ────────────────────────────────────────────────────────────────────────
    // LOGIN — SUKSES
    // ────────────────────────────────────────────────────────────────────────

    public function test_login_sukses_mengembalikan_token_dan_data_user(): void
    {
        $this->makeUser();

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'hrd@spk-absensi.local',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'token',
                'user' => ['id', 'name', 'email', 'role'],
            ])
            ->assertJsonPath('user.email', 'hrd@spk-absensi.local')
            ->assertJsonPath('user.role', 'hrd');

        $this->assertNotEmpty($response->json('token'));
    }

    // ────────────────────────────────────────────────────────────────────────
    // LOGIN — GAGAL
    // ────────────────────────────────────────────────────────────────────────

    public function test_login_gagal_password_salah_mengembalikan_401(): void
    {
        $this->makeUser();

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'hrd@spk-absensi.local',
            'password' => 'password-salah',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('message', 'Email atau password salah.');
    }

    public function test_login_gagal_email_tidak_terdaftar(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'tidakada@example.com',
            'password' => 'apapun',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('message', 'Email atau password salah.');
    }

    // ────────────────────────────────────────────────────────────────────────
    // LOGIN — VALIDASI
    // ────────────────────────────────────────────────────────────────────────

    public function test_login_validasi_wajib_email_dan_password(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_validasi_pesan_bahasa_indonesia(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'bukan-email',
            'password' => '',
        ]);

        $response->assertUnprocessable();
        $errors = $response->json('errors');
        $this->assertStringContainsString('valid', strtolower($errors['email'][0]));
        $this->assertStringContainsString('wajib', strtolower($errors['password'][0]));
    }

    // ────────────────────────────────────────────────────────────────────────
    // LOGIN — RATE LIMITING
    // ────────────────────────────────────────────────────────────────────────

    public function test_login_rate_limiting_blokir_setelah_5_percobaan_gagal(): void
    {
        $this->makeUser();

        // 5 percobaan gagal
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'email'    => 'hrd@spk-absensi.local',
                'password' => 'salah',
            ]);
        }

        // Percobaan ke-6 harus kena rate limit (429)
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'hrd@spk-absensi.local',
            'password' => 'salah',
        ]);

        $response->assertStatus(429);
        $this->assertStringContainsString(
            'Terlalu banyak percobaan',
            $response->json('message')
        );
    }

    public function test_login_berhasil_setelah_rate_limit_di_reset(): void
    {
        $this->makeUser();

        // Penuhi rate limit
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'email'    => 'hrd@spk-absensi.local',
                'password' => 'salah',
            ]);
        }

        // Reset manual (simulasi decay)
        RateLimiter::clear('hrd@spk-absensi.local|127.0.0.1');

        // Sekarang harus bisa login lagi
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'hrd@spk-absensi.local',
            'password' => 'secret123',
        ]);

        $response->assertOk();
    }

    // ────────────────────────────────────────────────────────────────────────
    // ME
    // ────────────────────────────────────────────────────────────────────────

    public function test_me_mengembalikan_data_user_terautentikasi(): void
    {
        $user = $this->makeUser();

        // Sanctum::actingAs mensimulasikan user dengan token valid
        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonStructure(['id', 'name', 'email', 'role'])
            ->assertJsonPath('email', 'hrd@spk-absensi.local')
            ->assertJsonPath('role', 'hrd');
    }

    public function test_me_tanpa_token_mengembalikan_401(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertUnauthorized();
    }

    // ────────────────────────────────────────────────────────────────────────
    // LOGOUT
    // ────────────────────────────────────────────────────────────────────────

    public function test_logout_mencabut_token_aktif(): void
    {
        $user = $this->makeUser();

        // Buat token nyata langsung (tidak via login endpoint)
        // agar kita bisa verifikasi token di database secara tepat
        $tokenResult = $user->createToken('test-session');
        $plainToken  = $tokenResult->plainTextToken;

        // Verifikasi token ada di DB sebelum logout
        $this->assertDatabaseCount('personal_access_tokens', 1);

        // Logout — harus berhasil dan menghapus token
        $this->withToken($plainToken)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logout berhasil.');

        // Token harus sudah terhapus dari database
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logout_tanpa_token_mengembalikan_401(): void
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertUnauthorized();
    }

    // ────────────────────────────────────────────────────────────────────────
    // PROTEKSI ROUTE
    // ────────────────────────────────────────────────────────────────────────

    public function test_akses_me_tanpa_token_mengembalikan_401(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_akses_logout_tanpa_token_mengembalikan_401(): void
    {
        $this->postJson('/api/auth/logout')->assertUnauthorized();
    }
}
