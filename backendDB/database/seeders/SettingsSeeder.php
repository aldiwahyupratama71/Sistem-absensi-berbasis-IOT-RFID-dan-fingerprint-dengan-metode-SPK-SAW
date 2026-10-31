<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key'         => 'work_days',
                // 1=Senin, 2=Selasa, 3=Rabu, 4=Kamis, 5=Jumat
                // Disimpan sebagai JSON array of ISO day-of-week numbers
                'value'       => json_encode([1, 2, 3, 4, 5]),
                'keterangan'  => 'Hari kerja aktif (ISO: 1=Senin ... 7=Minggu)',
            ],
            [
                'key'         => 'jam_masuk',
                'value'       => '07:00',
                'keterangan'  => 'Jam masuk kerja. Di atas jam ini dihitung TERLAMBAT.',
            ],
            [
                'key'         => 'timezone',
                'value'       => 'Asia/Jakarta',
                'keterangan'  => 'Zona waktu yang digunakan seluruh sistem (WIB)',
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
