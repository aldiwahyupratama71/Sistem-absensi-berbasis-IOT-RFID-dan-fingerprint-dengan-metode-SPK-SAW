<?php

namespace App\Http\Controllers;

use App\Exports\EvaluationPeriodExport;
use App\Models\EvaluationPeriod;
use App\Models\SpkEvaluation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class ExportController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/evaluation-periods/{id}/export?format=pdf|xlsx
    // ─────────────────────────────────────────────────────────────────────────
    public function exportPeriod(Request $request, EvaluationPeriod $evaluationPeriod)
    {
        $format = strtolower($request->query('format', 'pdf'));
        abort_unless(in_array($format, ['pdf', 'xlsx']), 422, 'Format harus pdf atau xlsx.');

        // Tidak ada lagi relasi contract tunggal
        // $evaluationPeriod->load(['contract.employee']);

        // Build filename
        $from    = Carbon::parse($evaluationPeriod->tanggal_mulai)->format('Ymd');
        $to      = Carbon::parse($evaluationPeriod->tanggal_selesai)->format('Ymd');
        $today   = Carbon::today('Asia/Jakarta')->format('Ymd');
        $filename = "laporan-evaluasi-{$from}-{$to}-{$today}.{$format}";

        if ($format === 'xlsx') {
            return $this->exportXlsx($evaluationPeriod, $filename);
        }

        return $this->exportPdf($evaluationPeriod, $filename);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/evaluations/{id}/export  — PDF rincian per karyawan
    // ─────────────────────────────────────────────────────────────────────────
    public function exportEvaluation(SpkEvaluation $evaluation)
    {
        $evaluation->load(['employee', 'details.spkCriteria', 'evaluationPeriod']);

        $period = $evaluation->evaluationPeriod;

        // Build details array identik dengan EvaluationController::show
        $details = $evaluation->details->map(function ($detail) {
            $kriteria = $detail->spkCriteria;
            $raw = is_array($detail->jumlah_data_mentah)
                ? $detail->jumlah_data_mentah
                : (array) json_decode($detail->jumlah_data_mentah ?? '{}', true);

            $rumus = '';
            if ($kriteria->kode === 'C1') {
                $absent = $raw['absent_tanpa_keterangan'] ?? 0;
                $rumus  = "MAX(0, 100 - {$absent} * 60)";
            } elseif ($kriteria->kode === 'C2') {
                $t10    = $raw['telat_1_10_menit'] ?? 0;
                $tLebih = $raw['telat_lebih_10_menit'] ?? 0;
                $rumus  = "MAX(0, 100 - ({$t10}*10 + {$tLebih}*40))";
            } elseif ($kriteria->kode === 'C3') {
                $hadir  = $raw['total_hadir'] ?? 0;
                $finger = $raw['pakai_fingerprint'] ?? 0;
                $rumus  = $hadir == 0 ? 'Hadir 0 → 0' : "({$hadir} - {$finger}) / {$hadir} * 100";
            }

            return [
                'kode'                  => $kriteria->kode,
                'nama'                  => $kriteria->nama,
                'rumus_substitusi'      => $rumus,
                'nilai_mentah'          => (float) $detail->nilai_mentah,
                'nilai_normalisasi'     => (float) $detail->nilai_normalisasi,
                'bobot'                 => (float) $detail->bobot,
                'kontribusi'            => (float) $detail->kontribusi,
                'daftar_pemicu_penalti' => $raw['daftar_tanggal_pemicu_penalti'] ?? [],
            ];
        })->sortBy('kode')->values();

        // Gabungkan semua data mentah dari tiap detail menjadi satu array untuk tabel bawah
        $rawData = [];
        foreach ($evaluation->details as $d) {
            $raw = is_array($d->jumlah_data_mentah)
                ? $d->jumlah_data_mentah
                : (array) json_decode($d->jumlah_data_mentah ?? '{}', true);
            $rawData = array_merge($rawData, $raw);
        }

        $nama     = str()->slug($evaluation->employee->nama, '-');
        $from     = Carbon::parse($period->tanggal_mulai)->format('Ymd');
        $to       = Carbon::parse($period->tanggal_selesai)->format('Ymd');
        $today    = Carbon::today('Asia/Jakarta')->format('Ymd');
        $filename = "rincian-evaluasi-{$nama}-{$from}-{$to}-{$today}.pdf";

        $pdf = Pdf::loadView('exports.evaluation-detail', compact('evaluation', 'period', 'details', 'rawData'))
            ->setPaper('A4', 'portrait');

        return $pdf->download($filename);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function exportPdf(EvaluationPeriod $period, string $filename): Response
    {
        // Eager-load evaluasi + details untuk periode ini
        $evaluations = $period->spkEvaluations()->with(['employee', 'details.spkCriteria'])->get();

        $pdf = Pdf::loadView('exports.evaluation-period', compact('period', 'evaluations'))
            ->setPaper('A4', 'landscape');

        return $pdf->download($filename);
    }

    private function exportXlsx(EvaluationPeriod $period, string $filename)
    {
        return Excel::download(new EvaluationPeriodExport($period), $filename);
    }
}
