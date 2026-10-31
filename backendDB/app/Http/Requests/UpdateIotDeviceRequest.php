<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateIotDeviceRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'nama'   => ['sometimes', 'string', 'max:255'],
            'lokasi' => ['nullable', 'string', 'max:255'],
        ];
    }
}
