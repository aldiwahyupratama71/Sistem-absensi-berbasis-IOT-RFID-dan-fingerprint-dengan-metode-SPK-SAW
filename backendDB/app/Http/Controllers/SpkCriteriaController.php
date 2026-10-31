<?php

namespace App\Http\Controllers;

use App\Models\SpkCriteria;
use Illuminate\Http\Request;

class SpkCriteriaController extends Controller
{
    /**
     * GET /api/criteria
     * Read-only criteria endpoints
     */
    public function index()
    {
        $criterias = SpkCriteria::select(['id', 'kode', 'nama', 'bobot', 'tipe'])->get()
            ->map(function ($c) {
                $deskripsi = '';
                if ($c->kode === 'C1') {
                    $deskripsi = 'MAX(0, 100 - (absent_tanpa_keterangan * 60))';
                } elseif ($c->kode === 'C2') {
                    $deskripsi = 'MAX(0, 100 - (telat_1_10_menit * 10 + telat_>10_menit * 40))';
                } elseif ($c->kode === 'C3') {
                    $deskripsi = '(hadir - pakai_fingerprint) / hadir * 100';
                }
                
                return [
                    'id' => $c->id,
                    'kode' => $c->kode,
                    'nama' => $c->nama,
                    'bobot' => $c->bobot,
                    'tipe' => $c->tipe,
                    'deskripsi_rumus' => $deskripsi,
                ];
            });
        
        return response()->json($criterias);
    }
}
