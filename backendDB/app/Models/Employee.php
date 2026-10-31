<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'nip',
        'nama',
        'departemen',
        'jabatan',       // sebelumnya: posisi
        'status',
        'status_aktif',
    ];

    protected $casts = [
        'status_aktif' => 'boolean',
    ];

    public function cards()
    {
        return $this->hasMany(EmployeeCard::class);
    }

    public function fingerprints()
    {
        return $this->hasMany(Fingerprint::class);
    }

    public function attendance()
    {
        return $this->hasMany(Attendance::class);
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }

    public function spkEvaluations()
    {
        return $this->hasMany(SpkEvaluation::class);
    }
}
