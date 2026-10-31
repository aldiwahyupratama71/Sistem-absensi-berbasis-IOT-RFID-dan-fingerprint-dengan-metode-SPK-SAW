<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpkCriteria extends Model
{
    protected $table = 'spk_criteria';

    protected $fillable = ['kode', 'nama', 'bobot', 'tipe'];

    public function details()
    {
        return $this->hasMany(SpkEvaluationDetail::class);
    }
}
