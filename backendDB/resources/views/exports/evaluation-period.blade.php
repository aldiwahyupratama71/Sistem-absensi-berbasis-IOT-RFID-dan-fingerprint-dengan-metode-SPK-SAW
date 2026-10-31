<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Evaluasi SPK</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10pt; color: #1a1a2e; }

        /* ── Header ─────────────────────────────── */
        .header { text-align: center; border-bottom: 2px solid #1e3a5f; padding-bottom: 10px; margin-bottom: 14px; }
        .header h1 { font-size: 15pt; color: #1e3a5f; letter-spacing: 0.5px; }
        .header p  { font-size: 9pt; color: #555; margin-top: 3px; }

        /* ── Info Grid ───────────────────────────── */
        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .meta-table td { padding: 3px 8px; font-size: 9.5pt; }
        .meta-table .label { font-weight: bold; color: #1e3a5f; width: 30%; }

        /* ── Bobot snapshot ─────────────────────── */
        .bobot-box { background: #f0f4ff; border-left: 3px solid #1e3a5f;
                     padding: 6px 10px; margin-bottom: 14px; font-size: 9pt; }
        .bobot-box strong { display: block; margin-bottom: 4px; }

        /* ── Tabel Hasil ─────────────────────────── */
        table.hasil { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.hasil th { background: #1e3a5f; color: #fff; padding: 5px 6px;
                          text-align: center; font-size: 9pt; }
        table.hasil td { padding: 4px 6px; font-size: 9pt; border-bottom: 1px solid #ddd; }
        table.hasil tr:nth-child(even) td { background: #f5f7ff; }
        .num { text-align: center; }

        /* ── Kategori badges ─────────────────────── */
        .badge { display: inline-block; padding: 1px 6px; border-radius: 3px;
                 font-size: 8pt; font-weight: bold; }
        .positif         { background: #d1fae5; color: #065f46; }
        .pertimbangan    { background: #fef9c3; color: #713f12; }
        .perhatian       { background: #ffedd5; color: #7c2d12; }
        .evaluasi_khusus { background: #fee2e2; color: #7f1d1d; }

        /* ── Legend ──────────────────────────────── */
        .legend { margin-bottom: 14px; }
        .legend h3 { font-size: 10pt; color: #1e3a5f; margin-bottom: 5px; }
        .legend table { border-collapse: collapse; width: 100%; }
        .legend td { padding: 3px 8px; font-size: 9pt; border: 1px solid #ddd; }
        .legend .lhead { background: #f0f4ff; font-weight: bold; }

        /* ── Footer disclaimer ───────────────────── */
        .disclaimer { border-top: 1px solid #ccc; padding-top: 8px; font-size: 8pt;
                      color: #666; font-style: italic; margin-top: 4px; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>

    {{-- ══════════════ HEADER ══════════════ --}}
    <div class="header">
        <h1>LAPORAN EVALUASI KEDISIPLINAN KARYAWAN</h1>
        <p>Sistem Pendukung Keputusan — Metode SAW (Simple Additive Weighting)</p>
    </div>

    {{-- ══════════════ META INFO ══════════════ --}}
    <table class="meta-table">
        <tr>
            <td class="label">Periode Evaluasi</td>
            <td>: {{ \Carbon\Carbon::parse($period->tanggal_mulai)->translatedFormat('d F Y') }}
                s/d {{ \Carbon\Carbon::parse($period->tanggal_selesai)->translatedFormat('d F Y') }}</td>
            <td class="label">Dicetak</td>
            <td>: {{ \Carbon\Carbon::now('Asia/Jakarta')->translatedFormat('d F Y, H:i') }} WIB</td>
        </tr>
        <tr>
            <td class="label">Masa Kontrak</td>
            <td>: {{ \Carbon\Carbon::parse($period->kontrak_mulai)->translatedFormat('d F Y') }}
                s/d {{ \Carbon\Carbon::parse($period->kontrak_selesai)->translatedFormat('d F Y') }}</td>
            <td class="label">Status Periode</td>
            <td>: {{ ucfirst($period->status) }}</td>
        </tr>
        <tr>
            <td class="label">Status Evaluasi</td>
            <td>: {{ $period->status_evaluasi === 'sudah_dihitung' ? 'Sudah Dihitung' : 'Belum Dihitung' }}</td>
            <td></td><td></td>
        </tr>
    </table>

    {{-- ══════════════ BOBOT SNAPSHOT ══════════════ --}}
    <div class="bobot-box">
        <strong>Bobot Kriteria yang Dipakai</strong>
        @php
            $snapshot = $evaluations->first()?->bobot_snapshot ?? [];
            $c1b = isset($snapshot['C1']) ? ($snapshot['C1'] * 100) . '%' : '50%';
            $c2b = isset($snapshot['C2']) ? ($snapshot['C2'] * 100) . '%' : '35%';
            $c3b = isset($snapshot['C3']) ? ($snapshot['C3'] * 100) . '%' : '15%';
        @endphp
        C1 Kehadiran: <strong>{{ $c1b }}</strong> &nbsp;|&nbsp;
        C2 Ketepatan Waktu: <strong>{{ $c2b }}</strong> &nbsp;|&nbsp;
        C3 Kepatuhan ID Card: <strong>{{ $c3b }}</strong>
    </div>

    {{-- ══════════════ TABEL HASIL ══════════════ --}}
    <table class="hasil">
        <thead>
            <tr>
                <th style="width:4%">#</th>
                <th style="width:20%">Nama Karyawan</th>
                <th style="width:14%">Departemen</th>
                <th style="width:9%">C1 (%)</th>
                <th style="width:9%">C2 (%)</th>
                <th style="width:9%">C3 (%)</th>
                <th style="width:12%">Nilai Akhir</th>
                <th style="width:18%">Kategori</th>
            </tr>
        </thead>
        <tbody>
            @forelse($evaluations as $i => $eval)
                @php
                    $c1 = $eval->details->firstWhere('spkCriteria.kode', 'C1');
                    $c2 = $eval->details->firstWhere('spkCriteria.kode', 'C2');
                    $c3 = $eval->details->firstWhere('spkCriteria.kode', 'C3');
                    $kategoriClass = str_replace(' ', '_', $eval->kategori);
                    $kategoriLabel = match($eval->kategori) {
                        'positif'         => 'Positif',
                        'pertimbangan'    => 'Perlu Pertimbangan',
                        'perhatian'       => 'Perlu Perhatian',
                        'evaluasi_khusus' => 'Evaluasi Khusus',
                        default           => ucfirst($eval->kategori),
                    };
                @endphp
                <tr>
                    <td class="num">{{ $i + 1 }}</td>
                    <td>{{ $eval->employee->nama }}</td>
                    <td>{{ $eval->employee->departemen ?? '-' }}</td>
                    <td class="num">{{ number_format($c1?->nilai_mentah ?? 0, 1) }}</td>
                    <td class="num">{{ number_format($c2?->nilai_mentah ?? 0, 1) }}</td>
                    <td class="num">{{ number_format($c3?->nilai_mentah ?? 0, 1) }}</td>
                    <td class="num"><strong>{{ number_format($eval->nilai_akhir, 2) }}</strong></td>
                    <td class="num"><span class="badge {{ $kategoriClass }}">{{ $kategoriLabel }}</span></td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align:center;color:#999;">Belum ada data evaluasi.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- ══════════════ LEGEND ══════════════ --}}
    <div class="legend">
        <h3>Keterangan Kategori</h3>
        <table>
            <tr>
                <td class="lhead">Kategori</td>
                <td class="lhead">Rentang Nilai Akhir</td>
                <td class="lhead">Interpretasi</td>
            </tr>
            <tr>
                <td><span class="badge positif">Positif</span></td>
                <td>90 – 100</td>
                <td>Kedisiplinan sangat baik</td>
            </tr>
            <tr>
                <td><span class="badge pertimbangan">Perlu Pertimbangan</span></td>
                <td>80 – 89</td>
                <td>Cukup baik, perlu dipantau</td>
            </tr>
            <tr>
                <td><span class="badge perhatian">Perlu Perhatian</span></td>
                <td>70 – 79</td>
                <td>Ada indikator kurang memuaskan</td>
            </tr>
            <tr>
                <td><span class="badge evaluasi_khusus">Evaluasi Khusus</span></td>
                <td>&lt; 70</td>
                <td>Perlu tindak lanjut segera</td>
            </tr>
        </table>
    </div>

    {{-- ══════════════ DISCLAIMER ══════════════ --}}
    <div class="disclaimer">
        ⚠ <strong>Catatan Penting:</strong> Laporan ini merupakan <em>alat bantu pendukung keputusan</em> dan bukan keputusan final.
        Hasil evaluasi SAW di atas hanya mencerminkan data kehadiran secara kuantitatif.
        Keputusan tindak lanjut (perpanjangan kontrak, pembinaan, dll.) sepenuhnya menjadi kewenangan HRD
        dengan mempertimbangkan faktor kualitatif lainnya.
    </div>

</body>
</html>
