<?php
// Script kalkulasi SPK untuk semua periode
// Jalankan: php artisan tinker < scripts/recalculate_spk.php

$svc = app(\App\Services\SpkCalculationService::class);
$periods = \App\Models\EvaluationPeriod::with('contract')->get();
echo "Periode ditemukan: " . $periods->count() . PHP_EOL;
$distribusi = [];
foreach ($periods as $p) {
    $svc->calculate($p);
    $p->refresh();
    $eval = $p->spkEvaluation()->first();
    if ($eval) {
        $kat = $eval->kategori;
        $distribusi[$kat] = ($distribusi[$kat] ?? 0) + 1;
        echo "  Periode #{$p->id} (karyawan {$p->contract->employee_id}) -> {$eval->nilai_akhir}% [{$kat}]" . PHP_EOL;
    }
}
echo PHP_EOL . "=== Distribusi ===" . PHP_EOL;
foreach (['positif','pertimbangan','perhatian','evaluasi_khusus'] as $slug) {
    echo "  {$slug}: " . ($distribusi[$slug] ?? 0) . PHP_EOL;
}
