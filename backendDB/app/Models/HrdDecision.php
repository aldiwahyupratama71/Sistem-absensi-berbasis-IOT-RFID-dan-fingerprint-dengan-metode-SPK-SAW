<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrdDecision extends Model
{
    protected $table = 'hrd_decisions';

    protected $fillable = [
        'spk_evaluation_id',
        'decision',
        'catatan',
        'decided_by',
        'decided_at',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    public function spkEvaluation()
    {
        return $this->belongsTo(SpkEvaluation::class);
    }

    /**
     * User HRD yang membuat keputusan ini.
     */
    public function decidedBy()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
