<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeCard extends Model
{
    protected $fillable = ['employee_id', 'uid_rfid', 'status'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
