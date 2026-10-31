<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\EmployeeCardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\FingerprintController;
use App\Http\Controllers\IotDeviceController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\AbsenceReasonController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\EvaluationPeriodController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\SpkCriteriaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceStatusController;
use App\Http\Controllers\ExportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Semua route di sini di-prefix /api secara otomatis oleh bootstrap/app.php.
| Endpoint /api/attendance/tap KHUSUS ESP32, tidak perlu auth Sanctum.
|
*/

// ── Auth (publik) ──────────────────────────────────────────────────────────
Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->name('login');

    // Route di bawah ini butuh token Sanctum yang valid
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me',      [AuthController::class, 'me'])->name('me');
    });
});

// ── Route terproteksi (butuh Bearer token) ─────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    // Employees
    Route::apiResource('employees', EmployeeController::class);
    
    // Employee Cards (RFID)
    Route::get('employees/{employee}/cards', [EmployeeCardController::class, 'index'])->name('employees.cards.index');
    Route::post('employees/{employee}/cards', [EmployeeCardController::class, 'store'])->name('employees.cards.store');
    Route::patch('employees/{employee}/cards/{card}/status', [EmployeeCardController::class, 'updateStatus'])->name('employees.cards.update-status');

    // Fingerprints (baca & hapus saja via HRD)
    Route::get('employees/{employee}/fingerprints', [FingerprintController::class, 'index'])->name('employees.fingerprints.index');
    Route::post('employees/{employee}/fingerprints', [FingerprintController::class, 'store'])->name('employees.fingerprints.store');
    Route::delete('employees/{employee}/fingerprints/{fingerprint}', [FingerprintController::class, 'destroy'])->name('employees.fingerprints.destroy');

    // IoT Devices — `/status` harus SEBELUM apiResource agar tidak ditangkap {device}
    Route::get('devices/status', [DeviceStatusController::class, 'index'])->name('devices.status');
    Route::apiResource('devices', IotDeviceController::class);
    Route::post('devices/{device}/regenerate-key', [IotDeviceController::class, 'regenerateKey'])->name('devices.regenerate-key');

    // Holidays
    Route::apiResource('holidays', HolidayController::class);

    // Absence Reasons
    Route::get('absence-reasons', [AbsenceReasonController::class, 'index'])->name('absence-reasons.index');

    // Attendance
    Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::patch('attendance/{attendance}/reason', [AttendanceController::class, 'updateReason'])->name('attendance.update-reason');
    Route::get('attendance/unexplained-count', [AttendanceController::class, 'unexplainedCount'])->name('attendance.unexplained-count');

    // Contracts
    Route::get('contracts/groups', [ContractController::class, 'groups'])->name('contracts.groups');
    Route::apiResource('contracts', ContractController::class);

    // Evaluation Periods
    Route::apiResource('evaluation-periods', EvaluationPeriodController::class);
    Route::post('evaluation-periods/{evaluationPeriod}/calculate', [EvaluationPeriodController::class, 'calculate'])->name('evaluation-periods.calculate');
    Route::get('evaluation-periods/{evaluationPeriod}/results', [EvaluationPeriodController::class, 'results'])->name('evaluation-periods.results');
    Route::get('evaluation-periods/{evaluationPeriod}/members', [EvaluationPeriodController::class, 'members'])->name('evaluation-periods.members');

    // Evaluations
    Route::get('evaluations/{evaluation}', [EvaluationController::class, 'show'])->name('evaluations.show');
    Route::post('evaluations/{evaluation}/recalculate', [EvaluationController::class, 'recalculate'])->name('evaluations.recalculate');
    Route::put('evaluations/{evaluation}/decision', [EvaluationController::class, 'decision'])->name('evaluations.decision');

    // SPK Criteria
    Route::get('criteria', [SpkCriteriaController::class, 'index'])->name('criteria.index');

    // Employee Evaluation History
    Route::get('employees/{employee}/evaluation-history', [EmployeeController::class, 'evaluationHistory'])->name('employees.evaluation-history');

    // Dashboard
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
    Route::get('dashboard/attendance-trend', [DashboardController::class, 'attendanceTrend'])->name('dashboard.attendance-trend');
    Route::get('dashboard/recent-attendance', [DashboardController::class, 'recentAttendance'])->name('dashboard.recent-attendance');

    // Exports
    Route::get('evaluation-periods/{evaluationPeriod}/export', [ExportController::class, 'exportPeriod'])->name('evaluation-periods.export');
    Route::get('evaluations/{evaluation}/export', [ExportController::class, 'exportEvaluation'])->name('evaluations.export');
});

// ── Route Khusus Device (Auth via X-API-KEY) ───────────────────────────────
Route::middleware('device.auth')->group(function () {
    // Daftarkan finger ID baru (link dari device ke database)
    Route::post('devices/{device}/fingerprints', [FingerprintController::class, 'linkFromDevice'])->name('devices.fingerprints.link');
    
    // Heartbeat untuk IoT device update last_seen_at
    Route::post('devices/heartbeat', [IotDeviceController::class, 'heartbeat'])->name('devices.heartbeat');

    // Absensi (tap) ESP32
    Route::post('attendance/tap', [AttendanceController::class, 'tap'])->name('attendance.tap');
});
