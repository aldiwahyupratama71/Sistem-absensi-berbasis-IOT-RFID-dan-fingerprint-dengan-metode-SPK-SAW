<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $table = 'attendance';

    protected $fillable = [
        'employee_id',
        'iot_device_id',
        'tanggal',
        'jam_masuk',
        'jam_keluar',
        'metode',
        'status',
        'absence_reason_id',
        'menit_terlambat',
        'is_auto_generated',
    ];

    protected $casts = [
        'tanggal'          => 'date',
        'is_auto_generated' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function iotDevice()
    {
        return $this->belongsTo(IotDevice::class);
    }

    public function absenceReason()
    {
        return $this->belongsTo(AbsenceReason::class);
    }
}
