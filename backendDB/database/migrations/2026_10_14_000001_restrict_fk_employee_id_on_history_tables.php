<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ganti FK onDelete dari CASCADE/SET NULL menjadi RESTRICT pada tiga tabel riwayat.
 *
 * Rasional:
 * - attendance & contracts: CASCADE → RESTRICT
 *   Data absensi dan kontrak adalah riwayat audit — tidak boleh terhapus otomatis
 *   hanya karena karyawannya dihapus.
 * - spk_evaluations: SET NULL + nullable → RESTRICT + NOT NULL
 *   Setelah cek di produksi (2026-10-14): 0 baris NULL → aman diubah NOT NULL.
 *   Evaluasi tanpa referensi karyawan tidak bermakna (data yatim).
 *
 * Tabel employee_cards & fingerprints dibiarkan CASCADE (desain disengaja):
 *   kartu/fingerprint hanya valid selama karyawan ada.
 *
 * CATATAN: Migration ini TIDAK dijalankan otomatis di spk_absensi (production).
 * Jalankan setelah ekspor SQL backup: php artisan migrate --force
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. attendance ───────────────────────────────────────────────────
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->foreign('employee_id')
                  ->references('id')
                  ->on('employees')
                  ->restrictOnDelete()
                  ->restrictOnUpdate();
        });

        // ── 2. contracts ────────────────────────────────────────────────────
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->foreign('employee_id')
                  ->references('id')
                  ->on('employees')
                  ->restrictOnDelete()
                  ->restrictOnUpdate();
        });

        // ── 3. spk_evaluations ──────────────────────────────────────────────
        // 3a. Hapus FK lama (SET NULL)
        Schema::table('spk_evaluations', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
        });

        // 3b. Ubah kolom menjadi NOT NULL (aman: 0 baris NULL di produksi)
        DB::statement('ALTER TABLE spk_evaluations MODIFY COLUMN employee_id BIGINT UNSIGNED NOT NULL');

        // 3c. Buat FK baru dengan RESTRICT
        Schema::table('spk_evaluations', function (Blueprint $table) {
            $table->foreign('employee_id')
                  ->references('id')
                  ->on('employees')
                  ->restrictOnDelete()
                  ->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        // ── 3. spk_evaluations — kembalikan ke SET NULL + nullable ──────────
        Schema::table('spk_evaluations', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
        });

        DB::statement('ALTER TABLE spk_evaluations MODIFY COLUMN employee_id BIGINT UNSIGNED NULL DEFAULT NULL');

        Schema::table('spk_evaluations', function (Blueprint $table) {
            $table->foreign('employee_id')
                  ->references('id')
                  ->on('employees')
                  ->nullOnDelete();
        });

        // ── 2. contracts — kembalikan ke CASCADE ────────────────────────────
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->foreign('employee_id')
                  ->references('id')
                  ->on('employees')
                  ->cascadeOnDelete();
        });

        // ── 1. attendance — kembalikan ke CASCADE ───────────────────────────
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->foreign('employee_id')
                  ->references('id')
                  ->on('employees')
                  ->cascadeOnDelete();
        });
    }
};
