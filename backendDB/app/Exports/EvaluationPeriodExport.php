<?php

namespace App\Exports;

use App\Models\EvaluationPeriod;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Illuminate\Support\Collection;

/**
 * Multi-sheet export: sheet "Hasil" + sheet "Rincian"
 */
class EvaluationPeriodExport implements WithMultipleSheets
{
    public function __construct(protected EvaluationPeriod $period) {}

    public function sheets(): array
    {
        return [
            new HasilSheet($this->period),
            new RincianSheet($this->period),
        ];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Sheet 1: Hasil (ringkasan per karyawan)
// ─────────────────────────────────────────────────────────────────────────────
class HasilSheet implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles, WithColumnWidths
{
    protected Collection $evaluations;

    public function __construct(protected EvaluationPeriod $period)
    {
        $this->evaluations = $period->spkEvaluations()->with(['employee', 'details.spkCriteria'])->get();
    }

    public function title(): string { return 'Hasil'; }

    public function collection(): Collection
    {
        return $this->evaluations;
    }

    public function headings(): array
    {
        return [
            'No', 'Nama Karyawan', 'NIP', 'Departemen', 'Jabatan',
            'C1 Kehadiran (%)', 'C2 Ketepatan Waktu (%)', 'C3 Kepatuhan ID Card (%)',
            'Nilai Akhir', 'Kategori',
        ];
    }

    public function map($eval): array
    {
        static $no = 0;
        $no++;

        $c1 = $eval->details->firstWhere('spkCriteria.kode', 'C1');
        $c2 = $eval->details->firstWhere('spkCriteria.kode', 'C2');
        $c3 = $eval->details->firstWhere('spkCriteria.kode', 'C3');

        $kategoriLabel = match($eval->kategori) {
            'positif'         => 'Positif (90-100)',
            'pertimbangan'    => 'Perlu Pertimbangan (80-89)',
            'perhatian'       => 'Perlu Perhatian (70-79)',
            'evaluasi_khusus' => 'Evaluasi Khusus (<70)',
            default           => ucfirst($eval->kategori),
        };

        return [
            $no,
            $eval->employee->nama,
            $eval->employee->nip,
            $eval->employee->departemen ?? '-',
            $eval->employee->jabatan   ?? '-',
            $c1 ? round((float) $c1->nilai_mentah, 2) : 0,
            $c2 ? round((float) $c2->nilai_mentah, 2) : 0,
            $c3 ? round((float) $c3->nilai_mentah, 2) : 0,
            round((float) $eval->nilai_akhir, 2),
            $kategoriLabel,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A5F']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5, 'B' => 26, 'C' => 14, 'D' => 16, 'E' => 16,
            'F' => 18, 'G' => 22, 'H' => 22, 'I' => 12, 'J' => 28,
        ];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Sheet 2: Rincian (nilai per kriteria per karyawan)
// ─────────────────────────────────────────────────────────────────────────────
class RincianSheet implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths
{
    protected Collection $rows;

    public function __construct(protected EvaluationPeriod $period)
    {
        // Ambil semua evaluasi + details sekali (no N+1)
        $evals = $period->spkEvaluations()->with(['employee', 'details.spkCriteria'])->get();

        $rows = collect();
        foreach ($evals as $eval) {
            foreach ($eval->details->sortBy('spkCriteria.kode') as $detail) {
                $raw = is_array($detail->jumlah_data_mentah)
                    ? $detail->jumlah_data_mentah
                    : json_decode($detail->jumlah_data_mentah ?? '{}', true);

                $rows->push([
                    $eval->employee->nama,
                    $eval->employee->nip,
                    $detail->spkCriteria->kode,
                    $detail->spkCriteria->nama,
                    round((float) $detail->nilai_mentah, 2),
                    round((float) $detail->nilai_normalisasi, 4),
                    round((float) $detail->bobot * 100, 2),
                    round((float) $detail->kontribusi * 100, 4),
                    json_encode($raw, JSON_UNESCAPED_UNICODE),
                ]);
            }
        }

        $this->rows = $rows;
    }

    public function title(): string { return 'Rincian'; }

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Nama Karyawan', 'NIP',
            'Kode Kriteria', 'Nama Kriteria',
            'Nilai Mentah (0-100)', 'Normalisasi (0-1)',
            'Bobot (%)', 'Kontribusi (%)',
            'Data Mentah (JSON)',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A5F']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 26, 'B' => 14, 'C' => 14, 'D' => 24,
            'E' => 20, 'F' => 18, 'G' => 12, 'H' => 16, 'I' => 50,
        ];
    }
}
