<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iot_devices', function (Blueprint $table) {
            // Hapus index/unique lama sebelum rename
            $table->dropUnique(['api_key']);

            // Rename api_key -> api_key_hash (nilai di-hash bcrypt/sha256)
            $table->renameColumn('api_key', 'api_key_hash');

            // Tambah prefix untuk identifikasi visual (misal: "sk_ab12cd34")
            $table->string('api_key_prefix', 16)->nullable()->after('api_key_hash');

            // Buat unique index baru pada api_key_hash
            $table->unique('api_key_hash');

            // Rename last_seen -> last_seen_at (konvensi timestamp Laravel)
            $table->renameColumn('last_seen', 'last_seen_at');

            // api_key_hash tidak boleh di-expose ke response API
            // (ditangani di Model $hidden)
        });
    }

    public function down(): void
    {
        Schema::table('iot_devices', function (Blueprint $table) {
            $table->dropUnique(['api_key_hash']);
            $table->renameColumn('api_key_hash', 'api_key');
            $table->unique('api_key');
            $table->dropColumn('api_key_prefix');
            $table->renameColumn('last_seen_at', 'last_seen');
        });
    }
};
