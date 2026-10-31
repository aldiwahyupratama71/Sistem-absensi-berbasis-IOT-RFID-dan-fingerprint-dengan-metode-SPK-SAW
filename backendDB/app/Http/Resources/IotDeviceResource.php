<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IotDeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $thresholdSeconds = (int) config('attendance.device_online_threshold_seconds', 90);

        $isOnline = $this->last_seen_at !== null
            && $this->last_seen_at->greaterThan(now()->subSeconds($thresholdSeconds));

        return [
            'id'               => $this->id,
            'nama'             => $this->nama,
            'lokasi'           => $this->lokasi,
            'api_key_prefix'   => $this->api_key_prefix,
            // api_key_hash TIDAK di-expose (ada di $hidden pada model)
            'last_seen_at'     => $this->last_seen_at?->toISOString(),
            'status_koneksi'   => $isOnline ? 'online' : 'offline',
            'created_at'       => $this->created_at?->toISOString(),
            'updated_at'       => $this->updated_at?->toISOString(),
        ];
    }
}
