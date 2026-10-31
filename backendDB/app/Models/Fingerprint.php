<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fingerprint extends Model
{
    protected $fillable = ['employee_id', 'iot_device_id', 'finger_id'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function iotDevice()
    {
        return $this->belongsTo(IotDevice::class);
    }
}
