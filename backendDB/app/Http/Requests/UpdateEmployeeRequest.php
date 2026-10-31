<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'nip'          => ['sometimes', 'string', 'max:50', Rule::unique('employees', 'nip')->ignore($this->route('employee'))],
            'nama'         => ['sometimes', 'string', 'max:255'],
            'departemen'   => ['nullable', 'string', 'max:100'],
            'jabatan'      => ['nullable', 'string', 'max:100'],
            'status_aktif' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nip.unique' => 'NIP sudah digunakan karyawan lain.',
        ];
    }
}
