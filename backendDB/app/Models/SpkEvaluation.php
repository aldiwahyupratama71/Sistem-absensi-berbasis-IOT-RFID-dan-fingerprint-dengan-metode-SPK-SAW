<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpkEvaluation extends Model
{
    protected $table = 'spk_evaluations';

    protected $fillable = [
        'evaluation_period_id',
        'employee_id',
        'contract_id',
        'bobot_snapshot',
        'nilai_akhir',
        'kategori',
        'calculated_at',
    ];

    protected $casts = [
        'bobot_snapshot' => 'array',
        'calculated_at'  => 'datetime',
        'nilai_akhir'    => 'decimal:2',
    ];

    public function evaluationPeriod()
    {
        return $this->belongsTo(EvaluationPeriod::class);
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function details()
    {
        return $this->hasMany(SpkEvaluationDetail::class);
    }

    public function hrdDecision()
    {
        return $this->hasOne(HrdDecision::class);
    }
}
