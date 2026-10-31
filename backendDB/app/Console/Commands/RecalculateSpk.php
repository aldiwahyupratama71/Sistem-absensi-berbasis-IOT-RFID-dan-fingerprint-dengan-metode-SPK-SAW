<?php

namespace App\Console\Commands;

use App\Models\EvaluationPeriod;
use App\Services\SpkCalculationService;
use Illuminate\Console\Command;

class RecalculateSpk extends Command
{
    protected $signature   = 'spk:recalculate {--period= : ID periode tertentu, jika tidak diisi semua periode dihitung}';
    protected $description = 'Hitung ulang SPK untuk satu atau semua periode evaluasi';

    public function handle(SpkCalculationService $svc): int
    {
        $periodId = $this->option('period');

        $periods = $periodId
            ? EvaluationPeriod::with('contract')->where('id', $periodId)->get()
            : EvaluationPeriod::with('contract')->get();

        if ($periods->isEmpty()) {
            $this->warn('Tidak ada periode evaluasi yang ditemukan.');
            return self::FAILURE;
        }

        $this->info("Menghitung SPK untuk {$periods->count()} periode...");

        $distribusi = [];
        foreach ($periods as $p) {
            $this->line("  Menghitung periode #{$p->id}...");
            $svc->calculate($p);
            $p->refresh();
            $eval = $p->spkEvaluation()->first();
            if ($eval) {
                $kat = $eval->kategori;
                $distribusi[$kat] = ($distribusi[$kat] ?? 0) + 1;
                $this->line("    ✔ Periode #{$p->id} → <fg=green>{$eval->nilai_akhir}%</> [{$kat}]");
            }
        }

        $this->newLine();
        $this->info('📊 Distribusi Kategori:');
        $labelMap = [
            'positif'         => 'Positif        (≥90)',
            'pertimbangan'    => 'Pertimbangan (80–89)',
            'perhatian'       => 'Perhatian    (70–79)',
            'evaluasi_khusus' => 'Evaluasi Khusus (<70)',
        ];
        foreach ($labelMap as $slug => $label) {
            $count = $distribusi[$slug] ?? 0;
            $this->line("  {$label}: {$count}");
        }

        return self::SUCCESS;
    }
}
