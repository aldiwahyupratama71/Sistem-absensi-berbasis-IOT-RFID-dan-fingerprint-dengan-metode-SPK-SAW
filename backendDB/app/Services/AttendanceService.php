<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\EmployeeCard;
use App\Models\Fingerprint;
use App\Models\IotDevice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceService
{
    /**
     * Process tap from IoT device.
     */
    public function processTap(array $data, IotDevice $device)
    {
        $employee = null;
        if ($data['metode'] === 'RFID') {
            $card = EmployeeCard::where('uid_rfid', $data['identifier'])
                ->where('status', 'aktif')
                ->first();
            if ($card) {
                $employee = $card->employee;
            }
        } elseif ($data['metode'] === 'FINGERPRINT') {
            $finger = Fingerprint::where('finger_id', $data['identifier'])
                ->where('iot_device_id', $device->id)
                ->first();
            if ($finger) {
                $employee = $finger->employee;
            }
        }

        if (!$employee || !$employee->status_aktif) {
            return ['status' => 'not_found'];
        }

        $timestamp = Carbon::parse($data['timestamp'])->timezone('Asia/Jakarta');
        $tanggal = $timestamp->format('Y-m-d');
        $timeString = $timestamp->format('H:i:s');
        
        $batasMasuk = Carbon::parse($tanggal . ' 07:00:00', 'Asia/Jakarta');

        try {
            return DB::transaction(function () use ($employee, $device, $tanggal, $timeString, $batasMasuk, $data, $timestamp) {
                $attendance = Attendance::where('employee_id', $employee->id)
                    ->where('tanggal', $tanggal)
                    ->lockForUpdate()
                    ->first();

                if ($attendance && $attendance->jam_masuk) {
                    return [
                        'status' => 'ok',
                        'nama' => $employee->nama,
                        'info' => 'sudah_absen'
                    ];
                }

                $menitTerlambat = 0;
                $statusAbsen = 'HADIR';

                if ($timestamp->greaterThan($batasMasuk)) {
                    $statusAbsen = 'TERLAMBAT';
                    // Use absolute difference
                    $diffInSeconds = $timestamp->diffInSeconds($batasMasuk, true);
                    $menitTerlambat = (int) ceil($diffInSeconds / 60);
                }

                if ($attendance && $attendance->is_auto_generated) {
                    $attendance->update([
                        'iot_device_id' => $device->id,
                        'jam_masuk' => $timeString,
                        'metode' => $data['metode'],
                        'status' => $statusAbsen,
                        'menit_terlambat' => $menitTerlambat,
                        'is_auto_generated' => false,
                        'absence_reason_id' => null
                    ]);
                } else {
                    Attendance::create([
                        'employee_id' => $employee->id,
                        'iot_device_id' => $device->id,
                        'tanggal' => $tanggal,
                        'jam_masuk' => $timeString,
                        'metode' => $data['metode'],
                        'status' => $statusAbsen,
                        'menit_terlambat' => $menitTerlambat,
                        'is_auto_generated' => false,
                    ]);
                }

                return [
                    'status' => 'ok',
                    'nama' => $employee->nama
                ];
            });
        } catch (\Exception $e) {
            Log::error('Attendance tap error: ' . $e->getMessage());
            // In case of unique constraint violation (race condition)
            return [
                'status' => 'ok',
                'nama' => $employee->nama,
                'info' => 'sudah_absen'
            ];
        }
    }
}
