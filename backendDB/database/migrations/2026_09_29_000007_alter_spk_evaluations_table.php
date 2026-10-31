<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spk_evaluations', function (Blueprint $table) {
            // Tambah relasi ke karyawan yang dievaluasi
            // nullable dulu, lalu set NOT NULL setelah data ada (migrasi aman)
            $table->foreignId('employee_id')
                ->nullable()
                ->after('evaluation_period_id')
                ->constrained('employees')
                ->nullOnDelete();

            // Snapshot bobot saat evaluasi dihitung (audit trail)
            // Format: {"C1": 0.50, "C2": 0.35, "C3": 0.15}
            $table->json('bobot_snapshot')->nullable()->after('employee_id');

            // Rename dihitung_pada -> calculated_at (konvensi bahasa Inggris konsisten)
            $table->renameColumn('dihitung_pada', 'calculated_at');

            // Pastikan satu karyawan hanya 1 evaluasi per periode
            $table->unique(['evaluation_period_id', 'employee_id'], 'uq_evaluation_period_employee');
        });
    }

    public function down(): void
    {
        Schema::table('spk_evaluations', function (Blueprint $table) {
            $table->dropUnique('uq_evaluation_period_employee');
            $table->dropForeign(['employee_id']);
            $table->dropColumn(['employee_id', 'bobot_snapshot']);
            $table->renameColumn('calculated_at', 'dihitung_pada');
        });
    }
};
