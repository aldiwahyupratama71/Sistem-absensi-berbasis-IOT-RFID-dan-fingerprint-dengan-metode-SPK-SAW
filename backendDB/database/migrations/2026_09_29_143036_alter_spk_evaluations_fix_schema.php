<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fix schema spk_evaluations:
 * 1. Enum 'khusus' → 'evaluasi_khusus' (sesuai rules: 4 kategori)
 * 2. Tambah kolom employee_id (FK ke employees)
 * 3. Tambah kolom bobot_snapshot (JSON snapshot bobot saat kalkulasi)
 * 4. Rename dihitung_pada → calculated_at
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL ENUM tidak bisa dimodifikasi langsung di Laravel Blueprint untuk ALTER.
        // Gunakan raw SQL untuk mengubah ENUM.
        DB::statement("ALTER TABLE spk_evaluations MODIFY COLUMN kategori ENUM('positif','pertimbangan','perhatian','evaluasi_khusus') NOT NULL");

        Schema::table('spk_evaluations', function (Blueprint $table) {
            // Tambah employee_id jika belum ada
            if (!Schema::hasColumn('spk_evaluations', 'employee_id')) {
                $table->foreignId('employee_id')
                    ->nullable()
                    ->after('evaluation_period_id')
                    ->constrained('employees')
                    ->nullOnDelete();
            }

            // Tambah bobot_snapshot jika belum ada
            if (!Schema::hasColumn('spk_evaluations', 'bobot_snapshot')) {
                $table->json('bobot_snapshot')->nullable()->after('employee_id');
            }

            // Tambah calculated_at jika belum ada (alias dihitung_pada)
            if (!Schema::hasColumn('spk_evaluations', 'calculated_at')) {
                $table->timestamp('calculated_at')->nullable()->after('bobot_snapshot');
            }
        });
    }

    public function down(): void
    {
        // Kembalikan enum lama
        DB::statement("ALTER TABLE spk_evaluations MODIFY COLUMN kategori ENUM('positif','pertimbangan','perhatian','khusus') NOT NULL");

        Schema::table('spk_evaluations', function (Blueprint $table) {
            if (Schema::hasColumn('spk_evaluations', 'employee_id')) {
                $table->dropForeign(['employee_id']);
                $table->dropColumn('employee_id');
            }
            if (Schema::hasColumn('spk_evaluations', 'bobot_snapshot')) {
                $table->dropColumn('bobot_snapshot');
            }
            if (Schema::hasColumn('spk_evaluations', 'calculated_at')) {
                $table->dropColumn('calculated_at');
            }
        });
    }
};
