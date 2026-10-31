<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\SpkEvaluation;
use App\Models\HrdDecision;
use App\Services\SpkCalculationService;
use Illuminate\Http\Request;

class EvaluationController extends Controller
{
    public function show(SpkEvaluation $evaluation)
    {
        $evaluation->load(['employee', 'details.spkCriteria', 'hrdDecision', 'evaluationPeriod', 'contract']);

        return response()->json($this->buildShowResponse($evaluation));
    }

    public function recalculate(SpkEvaluation $evaluation, SpkCalculationService $service)
    {
        // Pastikan relasi tersedia sebelum hitung ulang (untuk snapshot 'sebelum')
        $evaluation->load(['evaluationPeriod', 'contract', 'details.spkCriteria', 'hrdDecision', 'employee']);

        $result = $service->recalculateOne($evaluation);

        // Reload relasi setelah hitung ulang
        $result['evaluation']->load(['employee', 'details.spkCriteria', 'hrdDecision', 'evaluationPeriod', 'contract']);

        $response = $this->buildShowResponse($result['evaluation']);

        // Tambahkan objek 'sebelum' ke response
        $sebelum = $result['sebelum'];
        $response['sebelum'] = [
            'nilai_akhir'   => $sebelum['nilai_akhir'],
            'kategori'      => $sebelum['kategori'],
            'calculated_at' => $sebelum['calculated_at'],
        ];

        return response()->json($response);
    }

    public function decision(Request $request, SpkEvaluation $evaluation)
    {
        $validated = $request->validate([
            'decision' => ['required', 'string'],
            'catatan'  => ['nullable', 'string'],
        ]);

        $decision = $evaluation->hrdDecision()->updateOrCreate(
            ['spk_evaluation_id' => $evaluation->id],
            [
                'decision'   => $validated['decision'],
                'catatan'    => $validated['catatan'] ?? null,
                'decided_by' => $request->user()->id ?? null,
                'decided_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Keputusan berhasil disimpan',
            'data'    => $decision
        ]);
    }

    // ── Private helper ────────────────────────────────────────────────────────

    /**
     * Bangun array response yang sama persis dengan format GET /api/evaluations/{id}.
     * Dipakai oleh show() dan recalculate() agar format selalu konsisten.
     */
    private function buildShowResponse(SpkEvaluation $evaluation): array
    {
        // Pastikan relasi sudah di-load sebelum memanggil method ini
        $kategori    = $evaluation->kategori;
        $rekomendasi = match ($kategori) {
            'positif'      => 'Performa sangat baik. Direkomendasikan untuk apresiasi atau bonus.',
            'pertimbangan' => 'Performa cukup baik. Perlu diperhatikan konsistensi di masa mendatang.',
            'perhatian'    => 'Terdapat beberapa indikator kehadiran/ketepatan waktu yang kurang memuaskan. Direkomendasikan untuk pembinaan.',
            default        => 'Performa sangat kurang. Direkomendasikan untuk evaluasi khusus atau peringatan.',
        };

        // ── Ambil data absensi dalam jendela evaluasi ────────────────────────
        $period       = $evaluation->evaluationPeriod;
        $tanggalMulai = $period?->tanggal_mulai?->format('Y-m-d') ?? '1970-01-01';
        $tanggalAkhir = $period?->tanggal_selesai?->format('Y-m-d') ?? '9999-12-31';

        $attendances = Attendance::with('absenceReason')
            ->where('employee_id', $evaluation->employee_id)
            ->whereBetween('tanggal', [$tanggalMulai, $tanggalAkhir])
            ->orderBy('tanggal')
            ->get();

        // ── Hitung ulang angka dari absensi (HANYA untuk bukti) ──────────────
        $totalHadir       = 0;
        $pakaiFingerprint = 0;
        $absentTanpaKet   = 0;
        $absentSah        = 0;
        $telat1_10        = 0;
        $telatLebih10     = 0;

        foreach ($attendances as $att) {
            if ($att->status === 'ABSENT') {
                if (is_null($att->absence_reason_id) || !$att->absenceReason?->is_sah) {
                    $absentTanpaKet++;
                } else {
                    $absentSah++;
                }
            } else {
                $totalHadir++;
                if ($att->status === 'TERLAMBAT') {
                    if ($att->menit_terlambat > 0 && $att->menit_terlambat <= 10) {
                        $telat1_10++;
                    } elseif ($att->menit_terlambat > 10) {
                        $telatLebih10++;
                    }
                }
                if ($att->metode === 'FINGERPRINT') {
                    $pakaiFingerprint++;
                }
            }
        }

        // ── Bangun data_mentah terstruktur ───────────────────────────────────
        $dataMentah = [
            'absent_tanpa_keterangan' => $absentTanpaKet,
            'absent_sah'              => $absentSah,
            'telat_1_10'              => $telat1_10,
            'telat_lebih_10'          => $telatLebih10,
            'total_hadir'             => $totalHadir,
            'pakai_fingerprint'       => $pakaiFingerprint,
        ];

        // ── Nama hari Indonesia ───────────────────────────────────────────────
        $hariIndonesia = [
            'Sunday'    => 'Minggu', 'Monday'   => 'Senin',  'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',   'Thursday' => 'Kamis',  'Friday'  => 'Jumat',
            'Saturday'  => 'Sabtu',
        ];

        // ── Bangun bukti_absensi ──────────────────────────────────────────────
        $buktiAbsensi = [];

        foreach ($attendances as $att) {
            $tanggalStr = $att->tanggal->format('Y-m-d');
            $hariNama   = $hariIndonesia[$att->tanggal->format('l')] ?? $att->tanggal->format('l');

            if ($att->status === 'ABSENT') {
                if (is_null($att->absence_reason_id) || !$att->absenceReason?->is_sah) {
                    $buktiAbsensi[] = [
                        'tanggal'         => $tanggalStr,
                        'hari'            => $hariNama,
                        'jam_masuk'       => null,
                        'metode'          => null,
                        'status'          => 'ABSENT',
                        'menit_terlambat' => 0,
                        'jenis'           => 'ABSEN_TANPA_KETERANGAN',
                        'kriteria'        => 'C1',
                        'poin_penalti'    => 60,
                    ];
                } else {
                    $buktiAbsensi[] = [
                        'tanggal'         => $tanggalStr,
                        'hari'            => $hariNama,
                        'jam_masuk'       => null,
                        'metode'          => null,
                        'status'          => 'ABSENT',
                        'menit_terlambat' => 0,
                        'jenis'           => 'ABSEN_SAH',
                        'kriteria'        => 'C1',
                        'poin_penalti'    => 0,
                    ];
                }
            } else {
                // Telat 1-10 menit → C2 penalti 10
                if ($att->status === 'TERLAMBAT' && $att->menit_terlambat > 0 && $att->menit_terlambat <= 10) {
                    $buktiAbsensi[] = [
                        'tanggal'         => $tanggalStr,
                        'hari'            => $hariNama,
                        'jam_masuk'       => $att->jam_masuk,
                        'metode'          => $att->metode,
                        'status'          => $att->status,
                        'menit_terlambat' => $att->menit_terlambat,
                        'jenis'           => 'TELAT_1_10',
                        'kriteria'        => 'C2',
                        'poin_penalti'    => 10,
                    ];
                }
                // Telat > 10 menit → C2 penalti 40
                elseif ($att->status === 'TERLAMBAT' && $att->menit_terlambat > 10) {
                    $buktiAbsensi[] = [
                        'tanggal'         => $tanggalStr,
                        'hari'            => $hariNama,
                        'jam_masuk'       => $att->jam_masuk,
                        'metode'          => $att->metode,
                        'status'          => $att->status,
                        'menit_terlambat' => $att->menit_terlambat,
                        'jenis'           => 'TELAT_LEBIH_10',
                        'kriteria'        => 'C2',
                        'poin_penalti'    => 40,
                    ];
                }

                // Fingerprint → C3
                if ($att->metode === 'FINGERPRINT') {
                    $poinFinger = $totalHadir > 0 ? round(100 / $totalHadir, 4) : 0;
                    $buktiAbsensi[] = [
                        'tanggal'         => $tanggalStr,
                        'hari'            => $hariNama,
                        'jam_masuk'       => $att->jam_masuk,
                        'metode'          => $att->metode,
                        'status'          => $att->status,
                        'menit_terlambat' => $att->menit_terlambat,
                        'jenis'           => 'FINGERPRINT',
                        'kriteria'        => 'C3',
                        'poin_penalti'    => $poinFinger,
                    ];
                }
            }
        }

        usort($buktiAbsensi, fn ($a, $b) => strcmp($a['tanggal'], $b['tanggal']));

        // ── Bangun details[] ──────────────────────────────────────────────────
        $details = $evaluation->details->map(function ($detail) {
            $kriteria = $detail->spkCriteria;
            $raw      = $detail->jumlah_data_mentah ?? [];

            $rumus = match ($kriteria->kode) {
                'C1' => (function () use ($raw) {
                    $absent = $raw['absent_tanpa_keterangan'] ?? 0;
                    return "MAX(0, 100 - {$absent} * 60)";
                })(),
                'C2' => (function () use ($raw) {
                    $t10    = $raw['telat_1_10_menit'] ?? 0;
                    $tLebih = $raw['telat_lebih_10_menit'] ?? 0;
                    return "MAX(0, 100 - ({$t10} * 10 + {$tLebih} * 40))";
                })(),
                'C3' => (function () use ($raw) {
                    $hadir  = $raw['total_hadir'] ?? 0;
                    $finger = $raw['pakai_fingerprint'] ?? 0;
                    return $hadir == 0
                        ? 'Hadir 0 -> 0'
                        : "({$hadir} - {$finger}) / {$hadir} * 100";
                })(),
                default => '',
            };

            return [
                'kode'                   => $kriteria->kode,
                'nama'                   => $kriteria->nama,
                'data_mentah'            => $raw,
                'nilai_mentah'           => $detail->nilai_mentah,
                'rumus_substitusi'       => $rumus,
                'nilai_normalisasi'      => $detail->nilai_normalisasi,
                'bobot'                  => $detail->bobot,
                'kontribusi'             => $detail->kontribusi,
                'daftar_pemicu_penalti'  => $raw['daftar_tanggal_pemicu_penalti'] ?? [],
            ];
        });

        // ── Bangun langkah_saw[] ──────────────────────────────────────────────
        $langkahSaw      = [];
        $totalKontribusi = 0.0;

        foreach ($evaluation->details->sortBy('spkCriteria.kode') as $detail) {
            $kode     = $detail->spkCriteria->kode;
            $raw      = $detail->jumlah_data_mentah ?? [];
            $dibatasi = false;

            if ($kode === 'C1') {
                $poinC1   = ($raw['absent_tanpa_keterangan'] ?? 0) * 60;
                $dibatasi = $poinC1 > 100;
            } elseif ($kode === 'C2') {
                $poinC2   = (($raw['telat_1_10_menit'] ?? 0) * 10) + (($raw['telat_lebih_10_menit'] ?? 0) * 40);
                $dibatasi = $poinC2 > 100;
            }

            $rumusSubstitusi = match ($kode) {
                'C1' => (function () use ($raw) {
                    $absent = $raw['absent_tanpa_keterangan'] ?? 0;
                    return "MAX(0, 100 - {$absent} * 60)";
                })(),
                'C2' => (function () use ($raw) {
                    $t10    = $raw['telat_1_10_menit'] ?? 0;
                    $tLebih = $raw['telat_lebih_10_menit'] ?? 0;
                    return "MAX(0, 100 - ({$t10} * 10 + {$tLebih} * 40))";
                })(),
                'C3' => (function () use ($raw) {
                    $hadir  = $raw['total_hadir'] ?? 0;
                    $finger = $raw['pakai_fingerprint'] ?? 0;
                    return $hadir == 0
                        ? 'Hadir 0 -> 0'
                        : "({$hadir} - {$finger}) / {$hadir} * 100";
                })(),
                default => '',
            };

            $kontribusi       = (float) $detail->kontribusi;
            $totalKontribusi += $kontribusi;

            $item = [
                'kode'             => $kode,
                'nama'             => $detail->spkCriteria->nama,
                'nilai_mentah'     => (float) $detail->nilai_mentah,
                'rumus_substitusi' => $rumusSubstitusi,
                'normalisasi'      => (float) $detail->nilai_normalisasi,
                'bobot'            => (float) $detail->bobot,
                'kontribusi'       => $kontribusi,
            ];

            if ($dibatasi) {
                $item['dibatasi'] = true;
            }

            $langkahSaw[] = $item;
        }

        $nilaiAkhirPersen = round($totalKontribusi * 100, 2);

        $langkahSawResult = [
            'langkah'            => $langkahSaw,
            'total_kontribusi'   => round($totalKontribusi, 4),
            'nilai_akhir_persen' => $nilaiAkhirPersen,
            'kategori'           => $evaluation->kategori,
        ];

        return [
            'id'            => $evaluation->id,
            'employee'      => $evaluation->employee,
            'nilai_akhir'   => $evaluation->nilai_akhir,
            'kategori'      => $evaluation->kategori,
            'rekomendasi'   => $rekomendasi,
            'calculated_at' => $evaluation->calculated_at,
            'details'       => $details,
            'langkah_saw'   => $langkahSawResult,
            'data_mentah'   => $dataMentah,
            'bukti_absensi' => $buktiAbsensi,
            'keputusan_hrd' => $evaluation->hrdDecision,
        ];
    }
}
