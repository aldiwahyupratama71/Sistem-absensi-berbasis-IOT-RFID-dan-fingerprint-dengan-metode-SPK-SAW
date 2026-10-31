<?php

namespace App\Http\Middleware;

use App\Models\IotDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDevice
{
    /**
     * Autentikasi ESP32 device via header X-API-KEY.
     *
     * Format header: X-API-KEY: {plain_api_key}
     * Backend: hash(sha256, plain_key), cari di api_key_hash.
     *
     * Route yang menggunakan middleware ini akan punya $request->device
     * sebagai instance IotDevice yang sudah terverifikasi.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $plainKey = $request->header('X-API-KEY');

        if (! $plainKey) {
            return response()->json([
                'message' => 'API key device tidak ditemukan.',
            ], 401);
        }

        $hashedKey = hash('sha256', $plainKey);

        $device = IotDevice::where('api_key_hash', $hashedKey)->first();

        if (! $device) {
            return response()->json([
                'message' => 'API key tidak valid.',
            ], 401);
        }

        // Update last_seen_at setiap request sukses
        $device->update(['last_seen_at' => now()]);

        // Suntik device ke request agar controller bisa langsung pakai
        $request->merge(['_authenticated_device' => $device]);
        $request->attributes->set('device', $device);

        return $next($request);
    }
}
