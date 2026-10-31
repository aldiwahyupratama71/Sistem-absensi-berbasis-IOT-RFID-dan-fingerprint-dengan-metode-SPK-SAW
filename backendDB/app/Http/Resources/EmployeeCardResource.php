<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'uid_rfid'   => $this->uid_rfid,
            'status'     => $this->status,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
