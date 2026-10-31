<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SpkCriteria;

class SpkCriteriaSeeder extends Seeder
{
    public function run(): void
    {
        SpkCriteria::updateOrCreate(
            ['kode' => 'C1'],
            ['nama' => 'Kehadiran', 'bobot' => 50.00, 'tipe' => 'benefit']
        );

        SpkCriteria::updateOrCreate(
            ['kode' => 'C2'],
            ['nama' => 'Ketepatan Waktu', 'bobot' => 35.00, 'tipe' => 'benefit']
        );

        SpkCriteria::updateOrCreate(
            ['kode' => 'C3'],
            ['nama' => 'Kepatuhan ID Card', 'bobot' => 15.00, 'tipe' => 'benefit']
        );
    }
}
