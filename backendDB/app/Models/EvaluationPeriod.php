<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationPeriod extends Model
{
    protected $fillable = ['kontrak_mulai', 'kontrak_selesai', 'tanggal_mulai', 'tanggal_selesai', 'status'];

    protected $casts = [
        'kontrak_mulai' => 'date:Y-m-d',
        'kontrak_selesai' => 'date:Y-m-d',
        'tanggal_mulai' => 'date:Y-m-d',
        'tanggal_selesai' => 'date:Y-m-d',
    ];

    protected $appends = ['nama', 'jumlah_anggota', 'jumlah_terhitung', 'status_evaluasi'];

    public function getNamaAttribute()
    {
        $mulai = $this->kontrak_mulai ? $this->kontrak_mulai->translatedFormat('d M Y') : '';
        $selesai = $this->kontrak_selesai ? $this->kontrak_selesai->translatedFormat('d M Y') : '';
        return "Kontrak {$mulai} – {$selesai}";
    }

    public function getJumlahAnggotaAttribute()
    {
        return \App\Models\Contract::where('tanggal_mulai', $this->kontrak_mulai)
            ->where('tanggal_selesai', $this->kontrak_selesai)
            ->count();
    }

    public function getJumlahTerhitungAttribute()
    {
        return $this->spkEvaluations()->count();
    }

    public function getStatusEvaluasiAttribute()
    {
        $anggota = $this->jumlah_anggota;
        $terhitung = $this->jumlah_terhitung;
        
        if ($terhitung == 0) return 'belum_dihitung';
        if ($terhitung >= $anggota && $anggota > 0) return 'sudah_dihitung';
        return 'sebagian_dihitung';
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class, 'tanggal_mulai', 'kontrak_mulai')
                    ->whereColumn('tanggal_selesai', 'evaluation_periods.kontrak_selesai');
    }

    public function spkEvaluations()
    {
        return $this->hasMany(SpkEvaluation::class);
    }
}
