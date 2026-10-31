<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Console\Command;

class MarkAbsentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:mark-absent {--date= : The date to mark absent for (Y-m-d)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark active employees without attendance as ABSENT for a specific date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dateStr = $this->option('date') ?: date('Y-m-d');
        $date = Carbon::parse($dateStr);

        // Check if it's a working day
        $hariKerja = config('attendance.hari_kerja', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']);
        if (!in_array($date->englishDayOfWeek, $hariKerja)) {
            $this->info("Skipping {$dateStr}: It is a weekend/non-working day ({$date->englishDayOfWeek}).");
            return;
        }

        // Check if it's a holiday
        if (Holiday::where('tanggal', $dateStr)->exists()) {
            $this->info("Skipping {$dateStr}: It is a registered holiday.");
            return;
        }

        // Get all active employees with an active contract on this date
        $employees = Employee::where('status_aktif', true)
            ->whereHas('contracts', function ($query) use ($dateStr) {
                $query->where('tanggal_mulai', '<=', $dateStr)
                    ->where(function ($q) use ($dateStr) {
                        $q->whereNull('tanggal_selesai')
                          ->orWhere('tanggal_selesai', '>=', $dateStr);
                    });
            })
            ->get();

        $markedCount = 0;

        foreach ($employees as $employee) {
            // Check if attendance already exists
            $attendanceExists = Attendance::where('employee_id', $employee->id)
                ->where('tanggal', $dateStr)
                ->exists();

            if (!$attendanceExists) {
                Attendance::create([
                    'employee_id' => $employee->id,
                    'tanggal' => $dateStr,
                    'status' => 'ABSENT',
                    'is_auto_generated' => true,
                    'absence_reason_id' => null,
                ]);
                $markedCount++;
            }
        }

        $this->info("Successfully marked {$markedCount} employees as ABSENT on {$dateStr}.");
    }
}
