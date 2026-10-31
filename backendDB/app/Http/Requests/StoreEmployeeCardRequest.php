<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeCardRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'uid_rfid' => ['required', 'string', 'max:50', 'unique:employee_cards,uid_rfid'],
        ];
    }

    public function messages(): array
    {
        return [
            'uid_rfid.required' => 'UID kartu RFID wajib diisi.',
            'uid_rfid.unique'   => 'UID kartu sudah terdaftar untuk karyawan lain.',
        ];
    }
}
