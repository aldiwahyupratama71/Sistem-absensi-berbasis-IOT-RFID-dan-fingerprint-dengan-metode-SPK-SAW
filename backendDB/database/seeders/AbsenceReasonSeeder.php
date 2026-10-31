<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AbsenceReason;

class AbsenceReasonSeeder extends Seeder
{
    public function run(): void
    {
        $reasons = [
            ['kode' => 'MARRIAGE',      'nama' => 'Menikah',                'is_sah' => true],
            ['kode' => 'FAMILY_DEATH',  'nama' => 'Keluarga Meninggal',     'is_sah' => true],
            ['kode' => 'HOSPITALIZED',  'nama' => 'Dirawat di Rumah Sakit', 'is_sah' => true],
            ['kode' => 'NO_REASON',     'nama' => 'Tanpa Keterangan',       'is_sah' => false],
        ];

        foreach ($reasons as $reason) {
            AbsenceReason::updateOrCreate(['kode' => $reason['kode']], $reason);
        }
    }
}
