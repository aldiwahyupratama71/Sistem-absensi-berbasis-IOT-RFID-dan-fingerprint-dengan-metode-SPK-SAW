<?php

namespace App\Services;

class SawCalculator
{
    /**
     * Hitung nilai mentah C1 (Kehadiran)
     * Rule: MAX(0, 100 - absent_tanpa_keterangan * 60)
     */
    public function c1(int $absentTanpaKeterangan): float
    {
        return max(0, 100 - ($absentTanpaKeterangan * 60));
    }

    /**
     * Hitung nilai mentah C2 (Ketepatan Waktu)
     * Rule: MAX(0, 100 - (telat_1_10_menit * 10 + telat_lebih_10_menit * 40))
     */
    public function c2(int $telat1_10, int $telatLebih10): float
    {
        return max(0, 100 - (($telat1_10 * 10) + ($telatLebih10 * 40)));
    }

    /**
     * Hitung nilai mentah C3 (Kepatuhan ID Card)
     * Rule: (hadir - pakai_fingerprint) / hadir * 100
     * Jika hadir 0, dikembalikan 0 untuk mencegah pembagian nol.
     */
    public function c3(int $hadir, int $pakaiFingerprint): float
    {
        if ($hadir === 0) {
            return 0;
        }
        
        return (($hadir - $pakaiFingerprint) / $hadir) * 100;
    }

    /**
     * Normalisasi nilai mentah (semua kriteria adalah BENEFIT dengan target maksimum 100)
     * Rule: nilai_mentah / 100
     */
    public function normalize(float $nilaiMentah): float
    {
        return $nilaiMentah / 100;
    }

    /**
     * Hitung nilai akhir berdasarkan bobot masing-masing kriteria.
     * @param array $normalizedScores Asosiatif array ['C1' => nilai, 'C2' => nilai, 'C3' => nilai]
     * @param array $weights Asosiatif array bobot dalam format persentase (e.g. ['C1' => 0.50, 'C2' => 0.35, 'C3' => 0.15])
     * @return float Nilai akhir dalam persen (e.g. 95.75)
     */
    public function finalScore(array $normalizedScores, array $weights): float
    {
        $score = 0;
        foreach ($normalizedScores as $key => $value) {
            if (isset($weights[$key])) {
                $score += $value * $weights[$key];
            }
        }
        return round($score * 100, 2);
    }

    /**
     * Tentukan kategori dari nilai akhir.
     * >= 90: Positif
     * >= 80: Perlu Pertimbangan
     * >= 70: Perlu Perhatian
     * < 70: Evaluasi Khusus
     */
    public function categorize(float $score): string
    {
        if ($score >= 90) {
            return 'Positif';
        } elseif ($score >= 80) {
            return 'Perlu Pertimbangan';
        } elseif ($score >= 70) {
            return 'Perlu Perhatian';
        } else {
            return 'Evaluasi Khusus';
        }
    }
}
