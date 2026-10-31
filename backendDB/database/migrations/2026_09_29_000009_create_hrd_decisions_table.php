<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hrd_decisions', function (Blueprint $table) {
            $table->id();

            // Satu evaluasi hanya boleh punya satu keputusan HRD
            $table->foreignId('spk_evaluation_id')
                ->unique()
                ->constrained('spk_evaluations')
                ->cascadeOnDelete();

            // Keputusan HRD — BUKAN hasil SPK, tapi tindak lanjut HRD
            // Sesuai rules: jangan pakai "Diterima/Ditolak", pakai label tindakan
            $table->enum('decision', ['DIPERPANJANG', 'TIDAK_DIPERPANJANG', 'DITUNDA']);

            // Catatan tambahan HRD (opsional)
            $table->text('catatan')->nullable();

            // User HRD yang membuat keputusan
            $table->foreignId('decided_by')
                ->constrained('users')
                ->restrictOnDelete();

            // Kapan keputusan dibuat
            $table->timestamp('decided_at')->useCurrent();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hrd_decisions');
    }
};
