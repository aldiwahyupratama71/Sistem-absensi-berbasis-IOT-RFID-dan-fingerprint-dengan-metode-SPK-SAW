<?php

namespace App\Console\Commands;

use App\Models\EvaluationPeriod;
use App\Models\SpkEvaluation;
use Illuminate\Console\Command;

class DiagnoseSpk extends Command
{
    protected $signature   = 'spk:diagnose {employee_id? : ID karyawan untuk detail lengkap}';
    protected $description = 'Tampilkan ringkasan dan detail SPK untuk diagnosis';

    public function handle(): int
    {
        // ── Ringkasan semua evaluasi ─────────────────────────────────────────
        $evals = SpkEvaluation::with(['employee', 'details.spkCriteria'])
            ->orderByDesc('nilai_akhir')
            ->get();

        $this->info("=== RINGKASAN SPK ({$evals->count()} evaluasi) ===");
        $this->newLine();

        $rows = [];
        foreach ($evals as $e) {
            $c1 = $e->details->firstWhere('spkCriteria.kode', 'C1');
            $c2 = $e->details->firstWhere('spkCriteria.kode', 'C2');
            $c3 = $e->details->firstWhere('spkCriteria.kode', 'C3');

            $rawC1 = $c1 ? json_decode($c1->jumlah_data_mentah, true) : [];
            $rawC2 = $c2 ? json_decode($c2->jumlah_data_mentah, true) : [];
            $rawC3 = $c3 ? json_decode($c3->jumlah_data_mentah, true) : [];

            $rows[] = [
                $e->employee->nip ?? '?',
                $e->employee->nama ?? '?',
                $e->employee->departemen ?? '?',
                number_format($e->nilai_akhir, 2) . '%',
                $e->kategori,
                $rawC1['absent_tanpa_keterangan'] ?? '-',
                $rawC2['telat_1_10_menit'] ?? '-',
                $rawC2['telat_lebih_10_menit'] ?? '-',
                $rawC3['total_hadir'] ?? '-',
                $rawC3['pakai_fingerprint'] ?? '-',
                $c1 ? number_format($c1->nilai_mentah, 1) : '-',
                $c2 ? number_format($c2->nilai_mentah, 1) : '-',
                $c3 ? number_format($c3->nilai_mentah, 1) : '-',
            ];
        }

        $this->table(
            ['NIP', 'Nama', 'Dept', 'Final', 'Kategori', 'AbsTK', 'T≤10', 'T>10', 'Hadir', 'FP', 'C1', 'C2', 'C3'],
            $rows
        );

        // ── Distribusi ───────────────────────────────────────────────────────
        $this->newLine();
        $this->info('=== DISTRIBUSI KATEGORI ===');
        $dist = $evals->groupBy('kategori')->map->count();
        foreach (['positif', 'pertimbangan', 'perhatian', 'evaluasi_khusus'] as $k) {
            $count = $dist[$k] ?? 0;
            $bar   = str_repeat('█', $count);
            $this->line("  {$k}: {$count}  {$bar}");
        }

        // ── Detail satu karyawan ─────────────────────────────────────────────
        $empId = $this->argument('employee_id');
        if ($empId) {
            $this->newLine();
            $this->info("=== DETAIL KARYAWAN ID={$empId} ===");
            $eval = $evals->firstWhere('employee_id', (int) $empId);
            if (!$eval) {
                $this->warn("Tidak ada evaluasi untuk employee_id={$empId}");
                return self::SUCCESS;
            }
            foreach ($eval->details as $d) {
                $raw = json_decode($d->jumlah_data_mentah, true);
                $this->line("  [{$d->spkCriteria->kode}] {$d->spkCriteria->nama}");
                $this->line("    Data Mentah  : " . json_encode($raw, JSON_UNESCAPED_UNICODE));
                $this->line("    Nilai Mentah : {$d->nilai_mentah}");
                $this->line("    Normalisasi  : {$d->nilai_normalisasi}");
                $this->line("    Bobot        : {$d->bobot}");
                $this->line("    Kontribusi   : {$d->kontribusi}");
            }
            $this->line("  Final Score  : {$eval->nilai_akhir}%");
            $this->line("  Kategori     : {$eval->kategori}");
        }

        return self::SUCCESS;
    }
}
