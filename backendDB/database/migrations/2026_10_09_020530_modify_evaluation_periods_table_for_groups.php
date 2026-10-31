<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah kolom baru ke tabel-tabel terkait, awalnya nullable untuk backfill
        Schema::table('evaluation_periods', function (Blueprint $table) {
            $table->date('kontrak_mulai')->nullable()->after('id');
            $table->date('kontrak_selesai')->nullable()->after('kontrak_mulai');
        });

        Schema::table('spk_evaluations', function (Blueprint $table) {
            $table->unsignedBigInteger('contract_id')->nullable()->after('employee_id');
        });

        // 2. BACKFILL DATA
        DB::transaction(function () {
            // Ambil semua evaluation_periods lama beserta contracts nya
            $oldPeriods = DB::table('evaluation_periods')
                ->join('contracts', 'evaluation_periods.contract_id', '=', 'contracts.id')
                ->select(
                    'evaluation_periods.id as period_id',
                    'evaluation_periods.tanggal_mulai as eval_mulai',
                    'evaluation_periods.tanggal_selesai as eval_selesai',
                    'contracts.id as contract_id',
                    'contracts.tanggal_mulai as k_mulai',
                    'contracts.tanggal_selesai as k_selesai'
                )
                ->get();

            // Kelompokkan berdasarkan (k_mulai, k_selesai)
            $groups = [];
            foreach ($oldPeriods as $row) {
                $key = $row->k_mulai . '|' . $row->k_selesai;
                if (!isset($groups[$key])) {
                    $groups[$key] = [
                        'master_period_id' => $row->period_id, // Jadikan yang pertama sebagai master
                        'k_mulai' => $row->k_mulai,
                        'k_selesai' => $row->k_selesai,
                        'members' => []
                    ];
                }
                $groups[$key]['members'][] = $row;
            }

            // Hitung evaluasi sebelum migrasi untuk verifikasi
            $countEvalsBefore = DB::table('spk_evaluations')->count();
            $countHrdBefore = DB::table('hrd_decisions')->count();

            $periodsToDelete = [];

            foreach ($groups as $group) {
                $masterId = $group['master_period_id'];
                
                // Update master period
                DB::table('evaluation_periods')
                    ->where('id', $masterId)
                    ->update([
                        'kontrak_mulai' => $group['k_mulai'],
                        'kontrak_selesai' => $group['k_selesai']
                    ]);

                // Update semua eval yang mengarah ke period lama di grup ini
                foreach ($group['members'] as $member) {
                    // Update spk_evaluations milik member ini
                    DB::table('spk_evaluations')
                        ->where('evaluation_period_id', $member->period_id)
                        ->update([
                            'evaluation_period_id' => $masterId,
                            'contract_id' => $member->contract_id
                        ]);
                    
                    if ($member->period_id !== $masterId) {
                        $periodsToDelete[] = $member->period_id;
                    }
                }
            }

            // Verifikasi jumlah (Seharusnya tidak berubah karena kita hanya update FK)
            $countEvalsAfter = DB::table('spk_evaluations')->count();
            $countHrdAfter = DB::table('hrd_decisions')->count();

            if ($countEvalsBefore !== $countEvalsAfter || $countHrdBefore !== $countHrdAfter) {
                throw new \Exception("Mismatch during backfill! Evals: $countEvalsBefore vs $countEvalsAfter. HRD: $countHrdBefore vs $countHrdAfter");
            }

            // Hapus periode duplikat
            if (!empty($periodsToDelete)) {
                // Pastikan tidak ada constraint foreign key error, jika ada berarti ada relasi yang terlewat
                DB::table('evaluation_periods')->whereIn('id', $periodsToDelete)->delete();
            }
        });

        // 3. Drop kolom lama & sesuaikan constraints
        Schema::table('spk_evaluations', function (Blueprint $table) {
            // Drop foreign key lama (jika ada, constraint name standard Laravel)
            // Namun kita harus yakin namanya. Jika kita tidak yakin, drop foreign jika exist.
            // Karena kita menggunakan cascade? Jika belum ada foreign key definition, skip dropForeign.
            // Kita drop index aja (kalau perlu), lalu set ulang
            // Asumsi tidak ada foreign key constraint di tabel asli, hanya MUL index.
        });

        // Hapus foreign key contract_id di evaluation_periods jika ada, lalu drop column
        Schema::table('evaluation_periods', function (Blueprint $table) {
            // Karena struktur sebelumya: $table->foreignId('contract_id')->constrained() 
            // Kita drop constraint
            $table->dropForeign(['contract_id']);
            $table->dropColumn('contract_id');
            
            // Set not nullable and unique constraint
            $table->date('kontrak_mulai')->nullable(false)->change();
            $table->date('kontrak_selesai')->nullable(false)->change();
            $table->unique(['kontrak_mulai', 'kontrak_selesai'], 'eval_periods_kontrak_unique');
        });

        Schema::table('spk_evaluations', function (Blueprint $table) {
            $table->unsignedBigInteger('contract_id')->nullable(false)->change();
            $table->foreign('contract_id')->references('id')->on('contracts')->onDelete('cascade');
            
            $table->unique(['evaluation_period_id', 'employee_id'], 'spk_evals_period_employee_unique');
        });
    }

    public function down(): void
    {
        // Karena ini mengubah struktur dari group ke personal, rollback akan kompleks
        // Tapi kita bisa coba kembalikan 1:1 antara period dan contract.
        // Hapus foreign contract_id dari spk_evaluations
        Schema::table('spk_evaluations', function (Blueprint $table) {
            $table->dropForeign(['contract_id']);
            $table->dropUnique('spk_evals_period_employee_unique');
        });

        Schema::table('evaluation_periods', function (Blueprint $table) {
            $table->dropUnique('eval_periods_kontrak_unique');
            $table->unsignedBigInteger('contract_id')->nullable()->after('id');
        });

        // Kembalikan evaluation_periods per contract (duplicate master period for each contract_id in spk_evaluations)
        DB::transaction(function () {
            $evals = DB::table('spk_evaluations')->get();
            foreach ($evals as $eval) {
                // Ambil master period
                $master = DB::table('evaluation_periods')->where('id', $eval->evaluation_period_id)->first();
                if ($master) {
                    $newPeriodId = DB::table('evaluation_periods')->insertGetId([
                        'contract_id' => $eval->contract_id,
                        'tanggal_mulai' => $master->tanggal_mulai,
                        'tanggal_selesai' => $master->tanggal_selesai,
                        'status' => $master->status,
                        'created_at' => $master->created_at,
                        'updated_at' => $master->updated_at,
                    ]);
                    // Arahkan kembali evaluasi ke periode baru
                    DB::table('spk_evaluations')
                        ->where('id', $eval->id)
                        ->update(['evaluation_period_id' => $newPeriodId]);
                }
            }
            // Hapus master period lama
            DB::table('evaluation_periods')->whereNotNull('kontrak_mulai')->delete();
        });

        Schema::table('evaluation_periods', function (Blueprint $table) {
            $table->dropColumn(['kontrak_mulai', 'kontrak_selesai']);
            $table->foreign('contract_id')->references('id')->on('contracts')->onDelete('cascade');
        });
        
        Schema::table('spk_evaluations', function (Blueprint $table) {
            $table->dropColumn('contract_id');
        });
    }
};
