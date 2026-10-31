<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fingerprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('iot_device_id')->constrained('iot_devices')->cascadeOnDelete();
            $table->unsignedInteger('finger_id'); // slot ID di modul AS608
            $table->timestamps();

            // Satu device tidak boleh punya finger_id yang sama dipakai 2 karyawan
            $table->unique(['iot_device_id', 'finger_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fingerprints');
    }
};
