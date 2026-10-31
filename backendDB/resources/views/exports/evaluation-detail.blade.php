<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rincian Evaluasi SPK</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9.5pt; color: #1a1a2e; }

        .header { text-align: center; border-bottom: 2px solid #1e3a5f; padding-bottom: 10px; margin-bottom: 14px; }
        .header h1 { font-size: 14pt; color: #1e3a5f; }
        .header h2 { font-size: 11pt; color: #444; margin-top: 4px; }
        .header p  { font-size: 8.5pt; color: #777; margin-top: 3px; }

        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .meta-table td { padding: 3px 8px; font-size: 9pt; }
        .meta-table .label { font-weight: bold; color: #1e3a5f; width: 28%; }

        .section-title { background: #1e3a5f; color: #fff; padding: 4px 8px;
                         font-size: 9.5pt; margin: 12px 0 6px; }

        table.detail { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.detail th { background: #dce8f7; color: #1e3a5f; padding: 4px 6px;
                          text-align: center; font-size: 8.5pt; }
        table.detail td { padding: 3px 6px; font-size: 8.5pt; border-bottom: 1px solid #e8e8e8; }
        .num { text-align: right; }
        .center { text-align: center; }
        .formula { font-family: "Courier New", monospace; font-size: 8pt; color: #374151; }

        .score-box { background: #f0f9ff; border: 1px solid #93c5fd;
                     padding: 8px 14px; margin-bottom: 14px; display: inline-block; }
        .score-box .val { font-size: 18pt; font-weight: bold; color: #1e3a5f; }
        .score-box .kat { font-size: 9pt; margin-top: 2px; }

        .badge { display: inline-block; padding: 1px 7px; border-radius: 3px;
                 font-size: 8.5pt; font-weight: bold; }
        .positif         { background: #d1fae5; color: #065f46; }
        .pertimbangan    { background: #fef9c3; color: #713f12; }
        .perhatian       { background: #ffedd5; color: #7c2d12; }
        .evaluasi_khusus { background: #fee2e2; color: #7f1d1d; }

        .disclaimer { border-top: 1px solid #ccc; padding-top: 8px; font-size: 8pt;
                      color: #666; font-style: italic; margin-top: 14px; }

        .penalty-list { font-size: 8pt; color: #666; }
    </style>
</head>
<body>

    <div class="header">
        <h1>RINCIAN EVALUASI KEDISIPLINAN KARYAWAN</h1>
        <h2>{{ $evaluation->employee->nama }}</h2>
        <p>Dicetak {{ \Carbon\Carbon::now('Asia/Jakarta')->translatedFormat('d F Y, H:i') }} WIB</p>
    </div>

    {{-- ══════════════ META ══════════════ --}}
    <table class="meta-table">
        <tr>
            <td class="label">Nama</td>
            <td>: {{ $evaluation->employee->nama }}</td>
            <td class="label">NIP</td>
            <td>: {{ $evaluation->employee->nip }}</td>
        </tr>
        <tr>
            <td class="label">Departemen</td>
            <td>: {{ $evaluation->employee->departemen ?? '-' }}</td>
            <td class="label">Jabatan</td>
            <td>: {{ $evaluation->employee->jabatan ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Periode Evaluasi</td>
            <td>: {{ \Carbon\Carbon::parse($period->tanggal_mulai)->translatedFormat('d M Y') }}
                – {{ \Carbon\Carbon::parse($period->tanggal_selesai)->translatedFormat('d M Y') }}</td>
            <td class="label">Dihitung Pada</td>
            <td>: {{ $evaluation->calculated_at
                    ? \Carbon\Carbon::parse($evaluation->calculated_at)->setTimezone('Asia/Jakarta')->translatedFormat('d M Y, H:i')
                    : '-' }}</td>
        </tr>
    </table>

    {{-- ══════════════ SKOR AKHIR ══════════════ --}}
    @php
        $kategoriLabel = match($evaluation->kategori) {
            'positif'         => 'Positif',
            'pertimbangan'    => 'Perlu Pertimbangan',
            'perhatian'       => 'Perlu Perhatian',
            'evaluasi_khusus' => 'Evaluasi Khusus',
            default           => ucfirst($evaluation->kategori),
        };
    @endphp
    <div class="score-box">
        <div>Nilai Akhir SAW</div>
        <div class="val">{{ number_format($evaluation->nilai_akhir, 2) }}</div>
        <div class="kat">
            <span class="badge {{ $evaluation->kategori }}">{{ $kategoriLabel }}</span>
        </div>
    </div>

    {{-- ══════════════ RINCIAN PER KRITERIA ══════════════ --}}
    <div class="section-title">Rincian Per Kriteria</div>
    <table class="detail">
        <thead>
            <tr>
                <th style="width:8%">Kode</th>
                <th style="width:22%">Kriteria</th>
                <th style="width:22%">Rumus (substitusi)</th>
                <th style="width:10%">Nilai Mentah</th>
                <th style="width:12%">Normalisasi</th>
                <th style="width:10%">Bobot</th>
                <th style="width:11%">Kontribusi</th>
            </tr>
        </thead>
        <tbody>
            @foreach($details as $d)
                <tr>
                    <td class="center"><strong>{{ $d['kode'] }}</strong></td>
                    <td>{{ $d['nama'] }}</td>
                    <td class="formula">{{ $d['rumus_substitusi'] }}</td>
                    <td class="num">{{ number_format($d['nilai_mentah'], 2) }}</td>
                    <td class="num">{{ number_format($d['nilai_normalisasi'], 4) }}</td>
                    <td class="num">{{ number_format($d['bobot'] * 100, 0) }}%</td>
                    <td class="num">{{ number_format($d['kontribusi'] * 100, 2) }}%</td>
                </tr>
                @if(!empty($d['daftar_pemicu_penalti']))
                <tr>
                    <td colspan="2"></td>
                    <td colspan="5" class="penalty-list">
                        ⚠ Tanggal pemicu penalti: {{ implode(', ', $d['daftar_pemicu_penalti']) }}
                    </td>
                </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    {{-- ══════════════ DATA MENTAH ══════════════ --}}
    <div class="section-title">Data Mentah Kehadiran</div>
    <table class="detail">
        <tr>
            <td class="label" style="width:40%">Total Hadir (HADIR + TERLAMBAT)</td>
            <td>{{ $rawData['total_hadir'] ?? '-' }} hari</td>
        </tr>
        <tr>
            <td class="label">Absent Tanpa Keterangan</td>
            <td>{{ $rawData['absent_tanpa_keterangan'] ?? '-' }} hari</td>
        </tr>
        <tr>
            <td class="label">Terlambat 1–10 Menit</td>
            <td>{{ $rawData['telat_1_10_menit'] ?? '-' }} kejadian</td>
        </tr>
        <tr>
            <td class="label">Terlambat &gt;10 Menit</td>
            <td>{{ $rawData['telat_lebih_10_menit'] ?? '-' }} kejadian</td>
        </tr>
        <tr>
            <td class="label">Menggunakan Fingerprint</td>
            <td>{{ $rawData['pakai_fingerprint'] ?? '-' }} hari</td>
        </tr>
    </table>

    {{-- ══════════════ DISCLAIMER ══════════════ --}}
    <div class="disclaimer">
        ⚠ <strong>Catatan Penting:</strong> Laporan ini merupakan <em>alat bantu pendukung keputusan</em> dan bukan keputusan final.
        Nilai SAW di atas hanya mencerminkan data kehadiran secara kuantitatif.
        Keputusan tindak lanjut sepenuhnya menjadi kewenangan HRD dengan mempertimbangkan faktor kualitatif lainnya.
    </div>

</body>
</html>
