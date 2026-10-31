<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Credential dibaca dari .env agar mudah diganti di production
        // tanpa mengubah kode.
        User::updateOrCreate(
            ['email' => env('HRD_EMAIL', 'hrd@spk-absensi.local')],
            [
                'name'     => 'Admin HRD',
                'email'    => env('HRD_EMAIL', 'hrd@spk-absensi.local'),
                'password' => bcrypt(env('HRD_PASSWORD', 'password')),
                'role'     => 'hrd',
            ]
        );
    }
}
