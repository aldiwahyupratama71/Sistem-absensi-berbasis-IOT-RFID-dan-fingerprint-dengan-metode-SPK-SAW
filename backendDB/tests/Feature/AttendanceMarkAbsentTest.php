<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AttendanceMarkAbsentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Set hari kerja to Mon-Fri for deterministic testing
        Config::set('attendance.hari_kerja', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']);
    }

    public function test_it_skips_weekends()
    {
        // 2026-09-26 is a Saturday
        Artisan::call('attendance:mark-absent', ['--date' => '2026-09-26']);
        
        $output = Artisan::output();
        $this->assertStringContainsString('weekend/non-working day', $output);
    }

    public function test_it_skips_holidays()
    {
        // 2026-09-28 is a Monday
        Holiday::create(['tanggal' => '2026-09-28', 'keterangan' => 'Libur Nasional']);
        
        Artisan::call('attendance:mark-absent', ['--date' => '2026-09-28']);
        
        $output = Artisan::output();
        $this->assertStringContainsString('registered holiday', $output);
    }

    public function test_it_marks_absent_for_active_employees_without_attendance()
    {
        // 2026-09-28 is a Monday
        $employee = Employee::create([
            'nama' => 'John Doe',
            'nip' => 'EMP-001',
            'jabatan' => 'Staff',
            'status_aktif' => true,
        ]);
        
        Contract::create([
            'employee_id' => $employee->id,
            'tanggal_mulai' => '2026-01-01',
            'tanggal_selesai' => '2026-12-31',
        ]);

        Artisan::call('attendance:mark-absent', ['--date' => '2026-09-28']);
        
        $this->assertDatabaseHas('attendance', [
            'employee_id' => $employee->id,
            'tanggal' => '2026-09-28',
            'status' => 'ABSENT',
            'is_auto_generated' => true,
            'absence_reason_id' => null,
        ]);
    }

    public function test_it_does_not_mark_absent_if_already_tapped()
    {
        $employee = Employee::create([
            'nama' => 'John Doe',
            'nip' => 'EMP-002',
            'jabatan' => 'Staff',
            'status_aktif' => true,
        ]);
        
        Contract::create([
            'employee_id' => $employee->id,
            'tanggal_mulai' => '2026-01-01',
            'tanggal_selesai' => '2026-12-31',
        ]);

        Attendance::create([
            'employee_id' => $employee->id,
            'tanggal' => '2026-09-28', // Monday
            'jam_masuk' => '08:00:00',
            'status' => 'HADIR',
        ]);

        Artisan::call('attendance:mark-absent', ['--date' => '2026-09-28']);
        
        // Should only have 1 record, not a new ABSENT record
        $this->assertDatabaseCount('attendance', 1);
        $this->assertDatabaseMissing('attendance', [
            'status' => 'ABSENT'
        ]);
    }

    public function test_it_does_not_mark_absent_for_employee_without_active_contract()
    {
        $employee = Employee::create([
            'nama' => 'John Doe',
            'nip' => 'EMP-003',
            'jabatan' => 'Staff',
            'status_aktif' => true,
        ]);
        
        // Contract ended in the past
        Contract::create([
            'employee_id' => $employee->id,
            'tanggal_mulai' => '2026-01-01',
            'tanggal_selesai' => '2026-08-31',
        ]);

        Artisan::call('attendance:mark-absent', ['--date' => '2026-09-28']);
        
        $this->assertDatabaseEmpty('attendance');
    }

    public function test_idempotency()
    {
        $employee = Employee::create([
            'nama' => 'John Doe',
            'nip' => 'EMP-004',
            'jabatan' => 'Staff',
            'status_aktif' => true,
        ]);
        
        Contract::create([
            'employee_id' => $employee->id,
            'tanggal_mulai' => '2026-01-01',
            'tanggal_selesai' => '2026-12-31',
        ]);

        Artisan::call('attendance:mark-absent', ['--date' => '2026-09-28']);
        Artisan::call('attendance:mark-absent', ['--date' => '2026-09-28']);
        
        // Should only have 1 ABSENT record
        $this->assertDatabaseCount('attendance', 1);
    }

    public function test_backfill_command()
    {
        $employee = Employee::create([
            'nama' => 'John Doe',
            'nip' => 'EMP-005',
            'jabatan' => 'Staff',
            'status_aktif' => true,
        ]);
        
        Contract::create([
            'employee_id' => $employee->id,
            'tanggal_mulai' => '2026-01-01',
            'tanggal_selesai' => '2026-12-31',
        ]);

        // Sept 24 (Thu) - Sept 28 (Mon)
        // 24 Thu -> ABSENT
        // 25 Fri -> ABSENT
        // 26 Sat -> Skip
        // 27 Sun -> Skip
        // 28 Mon -> ABSENT

        Artisan::call('attendance:backfill', [
            '--from' => '2026-09-24',
            '--to' => '2026-09-28',
        ]);

        $this->assertDatabaseCount('attendance', 3);
        $this->assertDatabaseHas('attendance', ['tanggal' => '2026-09-24']);
        $this->assertDatabaseHas('attendance', ['tanggal' => '2026-09-25']);
        $this->assertDatabaseHas('attendance', ['tanggal' => '2026-09-28']);
    }
}
