<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IotDevice extends Model
{
    use HasFactory;
    protected $fillable = [
        'nama',
        'lokasi',
        'api_key_hash',    // disimpan sebagai hash (sha256 atau bcrypt)
        'api_key_prefix',  // beberapa karakter awal untuk identifikasi visual
        'last_seen_at',
    ];

    /**
     * Atribut yang disembunyikan dari serialisasi API response.
     * api_key_hash TIDAK boleh di-expose ke frontend.
     */
    protected $hidden = ['api_key_hash'];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public function attendance()
    {
        return $this->hasMany(Attendance::class);
    }

    public function fingerprints()
    {
        return $this->hasMany(Fingerprint::class);
    }
}
