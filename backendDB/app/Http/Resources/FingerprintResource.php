<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FingerprintResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'finger_id'     => $this->finger_id,
            'iot_device_id' => $this->iot_device_id,
            'device_nama'   => $this->whenLoaded('iotDevice', fn() => $this->iotDevice->nama),
            'created_at'    => $this->created_at?->toISOString(),
        ];
    }
}
