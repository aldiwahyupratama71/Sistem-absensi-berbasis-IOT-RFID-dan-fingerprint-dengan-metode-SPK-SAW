<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'nip'          => $this->nip,
            'nama'         => $this->nama,
            'departemen'   => $this->departemen,
            'jabatan'      => $this->jabatan,
            'status'       => $this->status,
            'status_aktif' => $this->status_aktif,
            'kartu_rfid'   => EmployeeCardResource::collection($this->whenLoaded('cards')),
            'fingerprints' => FingerprintResource::collection($this->whenLoaded('fingerprints')),
            'created_at'   => $this->created_at?->toISOString(),
            'updated_at'   => $this->updated_at?->toISOString(),
        ];
    }
}
