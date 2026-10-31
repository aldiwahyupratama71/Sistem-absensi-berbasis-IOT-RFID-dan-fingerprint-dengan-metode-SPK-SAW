<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spk_evaluation_details', function (Blueprint $table) {
            // Rename nilai_terbobot -> kontribusi (semantik lebih akurat:
            // kontribusi = nilai_normalisasi * bobot untuk kriteria ini)
            $table->renameColumn('nilai_terbobot', 'kontribusi');

            // Bobot yang dipakai saat menghitung (snapshot per-kriteria)
            // Disimpan sebagai decimal agar presisi (misal: 0.5000)
            $table->decimal('bobot', 5, 4)->default(0)->after('nilai_normalisasi');

            // Rincian data mentah yang dipakai sebagai dasar perhitungan
            // Format berbeda per kriteria:
            // C1: {"total_hari_kerja": 22, "absent_tanpa_keterangan": 0}
            // C2: {"telat_1_10": 1, "telat_lebih_10": 0}
            // C3: {"hadir": 22, "pakai_fingerprint": 2}
            $table->json('jumlah_data_mentah')->nullable()->after('bobot');
        });
    }

    public function down(): void
    {
        Schema::table('spk_evaluation_details', function (Blueprint $table) {
            $table->renameColumn('kontribusi', 'nilai_terbobot');
            $table->dropColumn(['bobot', 'jumlah_data_mentah']);
        });
    }
};
