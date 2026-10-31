<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            // Tandai apakah record ini di-generate otomatis oleh scheduler
            // (misal: ABSENT otomatis saat karyawan tidak tap sama sekali)
            $table->boolean('is_auto_generated')->default(false)->after('menit_terlambat');

            // Index compound untuk query laporan per tanggal+status
            $table->index(['tanggal', 'status'], 'idx_attendance_tanggal_status');
        });
    }

    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropColumn('is_auto_generated');
            $table->dropIndex('idx_attendance_tanggal_status');
        });
    }
};
