<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AbsenceReason extends Model
{
    use HasFactory;

    protected $fillable = [
        'kode',
        'nama',
        'is_sah',
    ];

    protected $casts = [
        'is_sah' => 'boolean',
    ];
}
