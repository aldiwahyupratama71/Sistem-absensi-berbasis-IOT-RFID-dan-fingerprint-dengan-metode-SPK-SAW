<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absence_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique(); // MARRIAGE, FAMILY_DEATH, HOSPITALIZED, NO_REASON, dll
            $table->string('nama');
            $table->boolean('is_sah')->default(false); // true = tidak dihitung pelanggaran
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absence_reasons');
    }
};
