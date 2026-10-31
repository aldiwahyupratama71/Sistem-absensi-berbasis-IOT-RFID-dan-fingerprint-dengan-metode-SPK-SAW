<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    protected $fillable = ['employee_id', 'tanggal_mulai', 'tanggal_selesai', 'status'];

    protected $casts = [
        'tanggal_mulai' => 'date:Y-m-d',
        'tanggal_selesai' => 'date:Y-m-d',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function evaluationPeriods()
    {
        return $this->hasMany(EvaluationPeriod::class);
    }
}
