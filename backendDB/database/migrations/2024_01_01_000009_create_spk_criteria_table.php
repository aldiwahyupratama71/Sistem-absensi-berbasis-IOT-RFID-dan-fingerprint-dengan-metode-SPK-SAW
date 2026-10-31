<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spk_criteria', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique(); // C1, C2, C3
            $table->string('nama');
            $table->decimal('bobot', 5, 2); // 50.00 / 35.00 / 15.00
            $table->enum('tipe', ['benefit', 'cost'])->default('benefit');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spk_criteria');
    }
};
