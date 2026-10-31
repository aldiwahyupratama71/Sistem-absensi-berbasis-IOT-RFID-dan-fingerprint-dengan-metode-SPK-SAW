<?php

namespace App\Http\Controllers;

use App\Models\IotDevice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeviceStatusController extends Controller
{
    // Device dianggap aktif jika last_seen_at <= ACTIVE_MINUTES menit yang lalu
    private const ACTIVE_MINUTES = 15;

    /**
     * GET /api/devices/status
     */
    public function index()
    {
        $cutoff = Carbon::now('Asia/Jakarta')->subMinutes(self::ACTIVE_MINUTES);

        $devices = IotDevice::select('id', 'nama', 'lokasi', 'api_key_prefix', 'last_seen_at')
            ->orderBy('nama')
            ->get()
            ->map(function ($d) use ($cutoff) {
                $aktif = $d->last_seen_at !== null && $d->last_seen_at->gte($cutoff);
                return [
                    'id'               => $d->id,
                    'nama'             => $d->nama,
                    'lokasi'           => $d->lokasi,
                    'api_key_prefix'   => $d->api_key_prefix,
                    'last_seen_at'     => $d->last_seen_at,
                    'status_koneksi'   => $aktif ? 'aktif' : 'tidak_aktif',
                ];
            });

        return response()->json($devices);
    }
}
