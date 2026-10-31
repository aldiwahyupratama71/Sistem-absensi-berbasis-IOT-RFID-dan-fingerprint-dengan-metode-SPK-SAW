<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spk_evaluation_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spk_evaluation_id')->constrained('spk_evaluations')->cascadeOnDelete();
            $table->foreignId('spk_criteria_id')->constrained('spk_criteria')->cascadeOnDelete();
            $table->decimal('nilai_mentah', 5, 2);
            $table->decimal('nilai_normalisasi', 5, 4);
            $table->decimal('nilai_terbobot', 6, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spk_evaluation_details');
    }
};
