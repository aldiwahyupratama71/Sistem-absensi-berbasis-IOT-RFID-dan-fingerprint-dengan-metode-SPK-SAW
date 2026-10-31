<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('iot_device_id')->nullable()->constrained('iot_devices')->nullOnDelete();
            $table->date('tanggal');
            $table->time('jam_masuk')->nullable();
            $table->time('jam_keluar')->nullable();
            $table->enum('metode', ['RFID', 'FINGERPRINT'])->nullable();
            $table->enum('status', ['HADIR', 'TERLAMBAT', 'ABSENT']);
            $table->foreignId('absence_reason_id')->nullable()->constrained('absence_reasons')->nullOnDelete();
            $table->unsignedInteger('menit_terlambat')->default(0);
            $table->timestamps();

            // Satu karyawan cuma boleh punya 1 record per tanggal
            $table->unique(['employee_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance');
    }
};
