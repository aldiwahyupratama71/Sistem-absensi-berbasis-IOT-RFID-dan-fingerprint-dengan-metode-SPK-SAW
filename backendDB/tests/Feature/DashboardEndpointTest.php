<?php

namespace Tests\Feature;

use App\Models\AbsenceReason;
use App\Models\Attendance;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\EvaluationPeriod;
use App\Models\IotDevice;
use App\Models\SpkCriteria;
use App\Models\SpkEvaluation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardEndpointTest extends TestCase
{
    use RefreshDatabase;

    // ─── Data referensi yang bisa dihitung manual ─────────────────────────────
    // Tanggal absensi: 2026-01-10 (Sabtu dilewati, pakai hari kerja saja)
    private const REF_DATE = '2026-01-10'; // Sabtu — tapi seed-nya tetap masuk karena ini tes

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // SPK Criteria
        $this->c1 = SpkCriteria::create(['kode' => 'C1', 'nama' => 'Kehadiran',         'tipe' => 'benefit', 'bobot' => 50.00]);
        $this->c2 = SpkCriteria::create(['kode' => 'C2', 'nama' => 'Ketepatan Waktu',   'tipe' => 'benefit', 'bobot' => 35.00]);
        $this->c3 = SpkCriteria::create(['kode' => 'C3', 'nama' => 'Kepatuhan ID Card', 'tipe' => 'benefit', 'bobot' => 15.00]);

        // Absence reason
        $this->alasanSah = AbsenceReason::create(['kode' => 'S', 'nama' => 'Sakit', 'is_sah' => true]);

        // 3 karyawan aktif
        [$this->emp1, $this->emp2, $this->emp3] = collect(range(1, 3))->map(fn($i) => Employee::create([
            'nama' => "Karyawan $i", 'nip' => "E00$i", 'jabatan' => 'Staff', 'status_aktif' => true,
        ]))->all();

        // 1 karyawan nonaktif (tidak ikut hitungan)
        $this->empInactive = Employee::create([
            'nama' => 'Nonaktif', 'nip' => 'E999', 'jabatan' => 'Staff', 'status_aktif' => false,
        ]);

        // Contract + Period untuk emp1
        $this->contract = Contract::create([
            'employee_id' => $this->emp1->id,
            'tanggal_mulai' => '2026-01-01', 'tanggal_selesai' => '2026-01-31', 'status' => 'aktif',
        ]);
        $this->period = EvaluationPeriod::create([
            'kontrak_mulai' => '2026-01-01',
            'kontrak_selesai' => '2026-01-31',
            'tanggal_mulai' => '2026-01-01',
            'tanggal_selesai' => '2026-01-31',
            'status' => 'berjalan',
        ]);

        // ── Attendance pada REF_DATE ──────────────────────────────────────────
        // emp1: HADIR (rfid) → masuk hitungan tepat_waktu
        // emp2: TERLAMBAT (rfid) → masuk hitungan terlambat
        // emp3: ABSENT tanpa alasan → masuk tidak_hadir + peringatan absen_tanpa_alasan

        Attendance::create([
            'employee_id' => $this->emp1->id,
            'tanggal' => self::REF_DATE, 'jam_masuk' => '06:55:00',
            'status' => 'HADIR', 'metode' => 'rfid', 'menit_terlambat' => 0,
        ]);

        Attendance::create([
            'employee_id' => $this->emp2->id,
            'tanggal' => self::REF_DATE, 'jam_masuk' => '07:25:00',
            'status' => 'TERLAMBAT', 'metode' => 'rfid', 'menit_terlambat' => 25,
        ]);

        Attendance::create([
            'employee_id' => $this->emp3->id,
            'tanggal' => self::REF_DATE,
            'status' => 'ABSENT', 'metode' => null, 'is_auto_generated' => true,
        ]);

        // ── Attendance lebih awal (untuk rekap periode & trend) ───────────────
        // 2026-01-05: emp1 HADIR fingerprint, emp2 HADIR rfid
        Attendance::create([
            'employee_id' => $this->emp1->id,
            'tanggal' => '2026-01-05', 'jam_masuk' => '07:00:00',
            'status' => 'HADIR', 'metode' => 'fingerprint', 'menit_terlambat' => 0,
        ]);
        Attendance::create([
            'employee_id' => $this->emp2->id,
            'tanggal' => '2026-01-05', 'jam_masuk' => '07:00:00',
            'status' => 'HADIR', 'metode' => 'rfid', 'menit_terlambat' => 0,
        ]);

        // ── SPK Evaluation untuk period (emp1) ────────────────────────────────
        // C1 = 100, C2 = 80, C3 = 60 (angka mudah dihitung)
        $this->evaluation = SpkEvaluation::create([
            'evaluation_period_id' => $this->period->id,
            'employee_id' => $this->emp1->id,
            'contract_id' => $this->contract->id,
            'bobot_snapshot' => ['C1' => 0.5, 'C2' => 0.35, 'C3' => 0.15],
            'nilai_akhir' => 90.00,
            'kategori' => 'positif',
            'calculated_at' => now(),
        ]);
        $this->evaluation->details()->createMany([
            ['spk_criteria_id' => $this->c1->id, 'nilai_mentah' => 100, 'nilai_normalisasi' => 1.0, 'bobot' => 0.5,  'kontribusi' => 0.5,   'jumlah_data_mentah' => ['absent_tanpa_keterangan' => 0]],
            ['spk_criteria_id' => $this->c2->id, 'nilai_mentah' => 80,  'nilai_normalisasi' => 0.8, 'bobot' => 0.35, 'kontribusi' => 0.28,  'jumlah_data_mentah' => ['telat_1_10_menit' => 2, 'telat_lebih_10_menit' => 0]],
            ['spk_criteria_id' => $this->c3->id, 'nilai_mentah' => 60,  'nilai_normalisasi' => 0.6, 'bobot' => 0.15, 'kontribusi' => 0.09,  'jumlah_data_mentah' => ['total_hadir' => 5, 'pakai_fingerprint' => 2]],
        ]);

        // ── IoT Devices ───────────────────────────────────────────────────────
        // device1: aktif (last_seen 5 menit lalu)
        $this->devAktif = IotDevice::create([
            'nama' => 'Device Aktif', 'lokasi' => 'Lobi',
            'api_key_hash' => hash('sha256', 'key1'), 'api_key_prefix' => 'sk_aaa',
            'last_seen_at' => now()->subMinutes(5),
        ]);
        // device2: tidak aktif (last_seen 30 menit lalu)
        $this->devTidakAktif = IotDevice::create([
            'nama' => 'Device Mati', 'lokasi' => 'Gudang',
            'api_key_hash' => hash('sha256', 'key2'), 'api_key_prefix' => 'sk_bbb',
            'last_seen_at' => now()->subMinutes(30),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Auth guard
    // ─────────────────────────────────────────────────────────────────────────
    public function test_dashboard_requires_auth()
    {
        $this->getJson('/api/dashboard')->assertStatus(401);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/dashboard (tanpa period_id)
    // ─────────────────────────────────────────────────────────────────────────
    public function test_dashboard_kartu_counts_correctly()
    {
        $res = $this->actingAs($this->user, 'sanctum')->getJson('/api/dashboard');
        $res->assertStatus(200);

        // 3 karyawan aktif (empInactive tidak ikut)
        $res->assertJsonPath('kartu.total_karyawan_aktif', 3);

        // REF_DATE = 2026-01-10 adalah tanggal terakhir dengan data
        $res->assertJsonPath('kartu.tanggal_referensi', self::REF_DATE);

        // tepat_waktu = 1 (emp1 HADIR), terlambat = 1 (emp2), tidak_hadir = 1 (emp3)
        $res->assertJsonPath('kartu.tepat_waktu', 1);
        $res->assertJsonPath('kartu.terlambat', 1);
        $res->assertJsonPath('kartu.tidak_hadir', 1);

        // emp1 pakai rfid di REF_DATE → fingerprint = 0
        $res->assertJsonPath('kartu.absen_fingerprint', 0);
    }

    public function test_dashboard_peringatan_correctly()
    {
        $res = $this->actingAs($this->user, 'sanctum')->getJson('/api/dashboard');

        // emp3 ABSENT tanpa absence_reason_id
        $res->assertJsonPath('peringatan.absen_tanpa_alasan', 1);

        // device mati 1
        $res->assertJsonPath('peringatan.perangkat_tidak_aktif', 1);
    }

    public function test_dashboard_rekap_kehadiran_tanpa_period()
    {
        // Tanpa period_id → rekap 30 hari terakhir
        // Namun data kita mungkin di luar 30 hari jika REF_DATE terlalu lama
        // Buat data hari ini
        Attendance::create([
            'employee_id' => $this->emp1->id,
            'tanggal' => now()->toDateString(), 'jam_masuk' => '07:00:00',
            'status' => 'HADIR', 'metode' => 'rfid',
        ]);

        $res = $this->actingAs($this->user, 'sanctum')->getJson('/api/dashboard');
        $res->assertStatus(200);
        $res->assertJsonStructure(['rekap_kehadiran' => ['tepat_waktu', 'terlambat', 'tidak_hadir', 'total']]);

        // total >= 0 dan konsisten
        $rekap = $res->json('rekap_kehadiran');
        $this->assertEquals($rekap['total'], $rekap['tepat_waktu'] + $rekap['terlambat'] + $rekap['tidak_hadir']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/dashboard?period_id=  (dengan period)
    // ─────────────────────────────────────────────────────────────────────────
    public function test_dashboard_with_period_returns_period_info()
    {
        $res = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/dashboard?period_id={$this->period->id}");
        $res->assertStatus(200);

        $res->assertJsonPath('periode.id', $this->period->id);
        $res->assertJsonPath('periode.status_evaluasi', 'sudah_dihitung');
        $res->assertJsonPath('periode.grup_kontrak.kontrak_mulai', '2026-01-01');
    }

    public function test_dashboard_rata_rata_kriteria_manual_calc()
    {
        // Hanya 1 evaluasi dengan C1=100, C2=80, C3=60
        // Rata-rata masing-masing = nilai tersebut
        $res = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/dashboard?period_id={$this->period->id}");

        $res->assertStatus(200);

        // JSON numbers: 100.0 PHP float vs integer 100 from JSON → compare with ==
        $rataRata = $res->json('rata_rata_kriteria');
        $this->assertEquals(100.0, $rataRata['C1']);
        $this->assertEquals(80.0,  $rataRata['C2']);
        $this->assertEquals(60.0,  $rataRata['C3']);
    }

    public function test_dashboard_distribusi_kategori_semua_4_ada()
    {
        $res = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/dashboard?period_id={$this->period->id}");

        // Harus ada 4 kunci (termasuk yang 0)
        $dist = $res->json('distribusi_kategori');
        $this->assertArrayHasKey('positif', $dist);
        $this->assertArrayHasKey('pertimbangan', $dist);
        $this->assertArrayHasKey('perhatian', $dist);
        $this->assertArrayHasKey('evaluasi_khusus', $dist);

        // 1 evaluasi = positif
        $this->assertEquals(1, $dist['positif']['jumlah']);
        $this->assertEquals(100.0, $dist['positif']['persen']);
        $this->assertEquals(0, $dist['pertimbangan']['jumlah']);
        $this->assertEquals(0.0, $dist['pertimbangan']['persen']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/dashboard/attendance-trend
    // ─────────────────────────────────────────────────────────────────────────
    public function test_attendance_trend_returns_correct_range()
    {
        // Buat attendance hari ini agar ada data dalam range 7 hari
        Attendance::create([
            'employee_id' => $this->emp1->id,
            'tanggal' => now()->toDateString(),
            'status' => 'HADIR', 'metode' => 'rfid',
        ]);

        $res = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/dashboard/attendance-trend?range=7');

        $res->assertStatus(200);
        $data = $res->json();
        $this->assertCount(7, $data);
        // Setiap entri punya 4 field
        $this->assertArrayHasKey('tanggal', $data[0]);
        $this->assertArrayHasKey('hadir', $data[0]);
        $this->assertArrayHasKey('terlambat', $data[0]);
        $this->assertArrayHasKey('tidak_hadir', $data[0]);

        // Hari ini punya minimal 1 hadir
        $today = now()->toDateString();
        $todayData = collect($data)->firstWhere('tanggal', $today);
        $this->assertNotNull($todayData);
        $this->assertGreaterThanOrEqual(1, $todayData['hadir']);
    }

    public function test_attendance_trend_range_30_returns_30_days()
    {
        $res = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/dashboard/attendance-trend?range=30');
        $res->assertStatus(200);
        $this->assertCount(30, $res->json());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/dashboard/recent-attendance
    // ─────────────────────────────────────────────────────────────────────────
    public function test_recent_attendance_returns_limited_rows()
    {
        $res = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/dashboard/recent-attendance?limit=3');

        $res->assertStatus(200);
        // Kita punya 5 baris attendance, tapi limit=3
        $data = $res->json();
        $this->assertLessThanOrEqual(3, count($data));
        // Setiap baris punya relasi employee
        $this->assertArrayHasKey('employee', $data[0]);
        $this->assertArrayHasKey('nama', $data[0]['employee']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/devices/status
    // ─────────────────────────────────────────────────────────────────────────
    public function test_devices_status_requires_auth()
    {
        $this->getJson('/api/devices/status')->assertStatus(401);
    }

    public function test_devices_status_returns_correct_connection_status()
    {
        $res = $this->actingAs($this->user, 'sanctum')->getJson('/api/devices/status');

        $res->assertStatus(200);
        $data = collect($res->json());

        $aktif    = $data->firstWhere('nama', 'Device Aktif');
        $tidakAktif = $data->firstWhere('nama', 'Device Mati');

        $this->assertEquals('aktif',       $aktif['status_koneksi']);
        $this->assertEquals('tidak_aktif', $tidakAktif['status_koneksi']);

        // api_key_hash tidak boleh muncul di response
        $this->assertArrayNotHasKey('api_key_hash', $aktif);
    }
}
