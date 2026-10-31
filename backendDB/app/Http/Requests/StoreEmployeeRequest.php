<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'nip'        => ['required', 'string', 'max:50', 'unique:employees,nip'],
            'nama'       => ['required', 'string', 'max:255'],
            'departemen' => ['nullable', 'string', 'max:100'],
            'jabatan'    => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'nip.required'  => 'NIP wajib diisi.',
            'nip.unique'    => 'NIP sudah terdaftar.',
            'nama.required' => 'Nama karyawan wajib diisi.',
        ];
    }
}
