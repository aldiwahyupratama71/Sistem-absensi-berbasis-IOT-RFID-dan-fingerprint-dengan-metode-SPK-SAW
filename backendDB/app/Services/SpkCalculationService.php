<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Contract;
use App\Models\EvaluationPeriod;
use App\Models\SpkCriteria;
use App\Models\SpkEvaluation;
use App\Models\SpkEvaluationDetail;
use Illuminate\Support\Facades\DB;

class SpkCalculationService
{
    protected SawCalculator $calculator;

    public function __construct(SawCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    /**
     * Hitung SPK untuk 1 grup evaluasi (semua anggota)
     */
    public function calculateGroup(EvaluationPeriod $period): array
    {
        $contracts = Contract::where('tanggal_mulai', $period->kontrak_mulai)
            ->where('tanggal_selesai', $period->kontrak_selesai)
            ->get();

        $tanggalMulai   = $period->tanggal_mulai->format('Y-m-d');
        $tanggalSelesai = $period->tanggal_selesai->format('Y-m-d');

        $criterias       = SpkCriteria::all();
        $weights         = [];
        $criteriaModels  = [];

        foreach ($criterias as $c) {
            $weights[$c->kode]        = $c->bobot / 100;
            $criteriaModels[$c->kode] = $c;
        }

        $totalMembers = $contracts->count();
        $warnings     = [];
        $distribusi   = [
            'positif'          => 0,
            'pertimbangan'     => 0,
            'perhatian'        => 0,
            'evaluasi_khusus'  => 0,
        ];

        DB::transaction(function () use (
            $contracts, $period, $tanggalMulai, $tanggalSelesai,
            $weights, $criteriaModels, &$warnings, &$distribusi
        ) {
            // Hapus evaluasi yang employee-nya sudah tidak ada di grup kontrak ini
            $validEmployeeIds = $contracts->pluck('employee_id')->toArray();
            SpkEvaluation::where('evaluation_period_id', $period->id)
                ->whereNotIn('employee_id', $validEmployeeIds)
                ->delete();

            foreach ($contracts as $contract) {
                $employeeId = $contract->employee_id;

                $result = $this->calculateSingleEmployee(
                    $employeeId,
                    $contract->id,
                    $period->id,
                    $tanggalMulai,
                    $tanggalSelesai,
                    $weights,
                    $criteriaModels
                );

                if ($result['absent_tanpa_keterangan'] > 0) {
                    $warnings[] = "Karyawan ID {$employeeId} memiliki {$result['absent_tanpa_keterangan']} hari ABSENT tanpa keterangan";
                }

                $distribusi[$result['kategori']]++;
            }
        });

        return [
            'jumlah_karyawan'    => $totalMembers,
            'distribusi_kategori' => $distribusi,
            'warnings'           => $warnings,
        ];
    }

    /**
     * Hitung ulang SATU evaluasi berdasarkan id-nya.
     * id evaluasi dan hrd_decisions TIDAK berubah.
     * Evaluasi karyawan lain TIDAK tersentuh.
     *
     * @return array ['sebelum' => [...], 'evaluation' => SpkEvaluation]
     */
    public function recalculateOne(SpkEvaluation $evaluation): array
    {
        // Simpan snapshot nilai sebelum hitung ulang
        $sebelum = [
            'nilai_akhir'   => $evaluation->nilai_akhir,
            'kategori'      => $evaluation->kategori,
            'calculated_at' => $evaluation->calculated_at,
        ];

        $period = $evaluation->evaluationPeriod;
        $contract = $evaluation->contract;

        $tanggalMulai   = $period->tanggal_mulai->format('Y-m-d');
        $tanggalSelesai = $period->tanggal_selesai->format('Y-m-d');

        $criterias      = SpkCriteria::all();
        $weights        = [];
        $criteriaModels = [];

        foreach ($criterias as $c) {
            $weights[$c->kode]        = $c->bobot / 100;
            $criteriaModels[$c->kode] = $c;
        }

        DB::transaction(function () use (
            $evaluation, $contract, $period,
            $tanggalMulai, $tanggalSelesai,
            $weights, $criteriaModels
        ) {
            $this->calculateSingleEmployee(
                $evaluation->employee_id,
                $contract->id,
                $period->id,
                $tanggalMulai,
                $tanggalSelesai,
                $weights,
                $criteriaModels
            );
        });

        // Refresh model agar nilai baru terbaca
        $evaluation->refresh();

        return [
            'sebelum'    => $sebelum,
            'evaluation' => $evaluation,
        ];
    }

    /**
     * Hitung SPK satu karyawan dan simpan/update spk_evaluations + spk_evaluation_details.
     * Dipakai oleh calculateGroup() dan recalculateOne().
     *
     * @return array ringkasan hasil (dipakai calculateGroup untuk warning & distribusi)
     */
    private function calculateSingleEmployee(
        int    $employeeId,
        int    $contractId,
        int    $periodId,
        string $tanggalMulai,
        string $tanggalSelesai,
        array  $weights,
        array  $criteriaModels
    ): array {
        $attendances = Attendance::with('absenceReason')
            ->where('employee_id', $employeeId)
            ->whereBetween('tanggal', [$tanggalMulai, $tanggalSelesai])
            ->get();

        $absentTanpaKeterangan = 0;
        $absentSah             = 0;
        $hadir                 = 0;
        $telat1_10             = 0;
        $telatLebih10          = 0;
        $pakaiFingerprint      = 0;

        $pemicuC1 = [];
        $pemicuC2 = [];
        $pemicuC3 = [];

        foreach ($attendances as $att) {
            if ($att->status === 'ABSENT') {
                if (is_null($att->absence_reason_id) || !$att->absenceReason?->is_sah) {
                    $absentTanpaKeterangan++;
                    $pemicuC1[] = $att->tanggal->format('Y-m-d');
                } else {
                    $absentSah++;
                }
            } else {
                $hadir++;

                if ($att->status === 'TERLAMBAT') {
                    if ($att->menit_terlambat > 0 && $att->menit_terlambat <= 10) {
                        $telat1_10++;
                        $pemicuC2[] = $att->tanggal->format('Y-m-d') . " ({$att->menit_terlambat} mnt)";
                    } elseif ($att->menit_terlambat > 10) {
                        $telatLebih10++;
                        $pemicuC2[] = $att->tanggal->format('Y-m-d') . " ({$att->menit_terlambat} mnt)";
                    }
                }

                if ($att->metode === 'FINGERPRINT') {
                    $pakaiFingerprint++;
                    $pemicuC3[] = $att->tanggal->format('Y-m-d');
                }
            }
        }

        $rawScores = [
            'C1' => $this->calculator->c1($absentTanpaKeterangan),
            'C2' => $this->calculator->c2($telat1_10, $telatLebih10),
            'C3' => $this->calculator->c3($hadir, $pakaiFingerprint),
        ];

        $normalizedScores = [
            'C1' => $this->calculator->normalize($rawScores['C1']),
            'C2' => $this->calculator->normalize($rawScores['C2']),
            'C3' => $this->calculator->normalize($rawScores['C3']),
        ];

        $finalScore = $this->calculator->finalScore($normalizedScores, $weights);

        $kategoriMap = [
            'positif'            => 'positif',
            'perlu pertimbangan' => 'pertimbangan',
            'perlu perhatian'    => 'perhatian',
            'evaluasi khusus'    => 'evaluasi_khusus',
        ];
        $kategori = $kategoriMap[strtolower($this->calculator->categorize($finalScore))] ?? 'evaluasi_khusus';

        $details = [
            'C1' => [
                'jumlah_data_mentah' => [
                    'absent_tanpa_keterangan'      => $absentTanpaKeterangan,
                    'absent_sah'                   => $absentSah,
                    'daftar_tanggal_pemicu_penalti' => $pemicuC1,
                ],
                'nilai_mentah'      => $rawScores['C1'],
                'nilai_normalisasi' => $normalizedScores['C1'],
                'bobot'             => $weights['C1'] ?? 0,
                'kontribusi'        => $normalizedScores['C1'] * ($weights['C1'] ?? 0),
            ],
            'C2' => [
                'jumlah_data_mentah' => [
                    'telat_1_10_menit'             => $telat1_10,
                    'telat_lebih_10_menit'          => $telatLebih10,
                    'daftar_tanggal_pemicu_penalti' => $pemicuC2,
                ],
                'nilai_mentah'      => $rawScores['C2'],
                'nilai_normalisasi' => $normalizedScores['C2'],
                'bobot'             => $weights['C2'] ?? 0,
                'kontribusi'        => $normalizedScores['C2'] * ($weights['C2'] ?? 0),
            ],
            'C3' => [
                'jumlah_data_mentah' => [
                    'total_hadir'                  => $hadir,
                    'pakai_fingerprint'            => $pakaiFingerprint,
                    'daftar_tanggal_pemicu_penalti' => $pemicuC3,
                ],
                'nilai_mentah'      => $rawScores['C3'],
                'nilai_normalisasi' => $normalizedScores['C3'],
                'bobot'             => $weights['C3'] ?? 0,
                'kontribusi'        => $normalizedScores['C3'] * ($weights['C3'] ?? 0),
            ],
        ];

        if ($hadir === 0) {
            $details['C3']['jumlah_data_mentah']['catatan'] = 'Hadir 0, C3 otomatis 0';
        }

        $evaluation = SpkEvaluation::updateOrCreate(
            [
                'evaluation_period_id' => $periodId,
                'employee_id'          => $employeeId,
            ],
            [
                'contract_id'    => $contractId,
                'bobot_snapshot' => $weights,
                'nilai_akhir'    => $finalScore,
                'kategori'       => $kategori,
                'calculated_at'  => now(),
            ]
        );

        foreach (['C1', 'C2', 'C3'] as $kode) {
            if (isset($criteriaModels[$kode])) {
                SpkEvaluationDetail::updateOrCreate(
                    [
                        'spk_evaluation_id' => $evaluation->id,
                        'spk_criteria_id'   => $criteriaModels[$kode]->id,
                    ],
                    [
                        'nilai_mentah'      => $details[$kode]['nilai_mentah'],
                        'nilai_normalisasi' => $details[$kode]['nilai_normalisasi'],
                        'bobot'             => $details[$kode]['bobot'],
                        'jumlah_data_mentah' => $details[$kode]['jumlah_data_mentah'],
                        'kontribusi'        => $details[$kode]['kontribusi'],
                    ]
                );
            }
        }

        return [
            'absent_tanpa_keterangan' => $absentTanpaKeterangan,
            'kategori'                => $kategori,
        ];
    }
}
