<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpkEvaluationDetail extends Model
{
    protected $table = 'spk_evaluation_details';

    protected $fillable = [
        'spk_evaluation_id',
        'spk_criteria_id',
        'nilai_mentah',
        'nilai_normalisasi',
        'bobot',
        'kontribusi',            // sebelumnya: nilai_terbobot
        'jumlah_data_mentah',   // JSON rincian data mentah per kriteria
    ];

    protected $casts = [
        'jumlah_data_mentah' => 'array',
        'nilai_mentah'       => 'decimal:2',
        'nilai_normalisasi'  => 'decimal:4',
        'bobot'              => 'decimal:4',
        'kontribusi'         => 'decimal:4',
    ];

    public function spkEvaluation()
    {
        return $this->belongsTo(SpkEvaluation::class);
    }

    public function spkCriteria()
    {
        return $this->belongsTo(SpkCriteria::class);
    }
}
