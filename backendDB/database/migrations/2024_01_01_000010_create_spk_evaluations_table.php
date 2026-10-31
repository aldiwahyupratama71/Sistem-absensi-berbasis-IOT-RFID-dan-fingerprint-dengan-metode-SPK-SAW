<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spk_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_period_id')->constrained('evaluation_periods')->cascadeOnDelete();
            $table->decimal('nilai_akhir', 5, 2);
            $table->enum('kategori', ['positif', 'pertimbangan', 'perhatian', 'khusus']);
            $table->timestamp('dihitung_pada')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spk_evaluations');
    }
};
