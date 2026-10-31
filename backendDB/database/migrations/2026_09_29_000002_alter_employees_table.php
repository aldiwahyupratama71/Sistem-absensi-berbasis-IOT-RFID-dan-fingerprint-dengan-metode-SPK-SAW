<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Ubah departemen menjadi nullable (sebelumnya NOT NULL)
            $table->string('departemen')->nullable()->change();

            // Rename posisi -> jabatan (konsisten dengan kebutuhan rules)
            $table->renameColumn('posisi', 'jabatan');

            // Tambah status_aktif boolean (boolean, lebih sederhana dari enum)
            // Kolom status (enum aktif/nonaktif) tetap dipertahankan agar tidak merusak data lama
            $table->boolean('status_aktif')->default(true)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('departemen')->nullable(false)->change();
            $table->renameColumn('jabatan', 'posisi');
            $table->dropColumn('status_aktif');
        });
    }
};
