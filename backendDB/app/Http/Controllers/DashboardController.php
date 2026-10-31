<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\EvaluationPeriod;
use App\Models\Holiday;
use App\Models\IotDevice;
use App\Models\SpkEvaluationDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    // Batas menit "aktif" untuk device: jika last_seen_at > now - DEVICE_ACTIVE_MINUTES → aktif
    private const DEVICE_ACTIVE_MINUTES = 15;

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/dashboard?period_id=
    // ─────────────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $period = null;
        if ($request->filled('period_id')) {
            $period = EvaluationPeriod::findOrFail($request->period_id);
        }

        return response()->json([
            'kartu'               => $this->kartu(),
            'rekap_kehadiran'     => $this->rekapKehadiran($period),
            'rata_rata_kriteria'  => $this->rataRataKriteria($period),
            'distribusi_kategori' => $this->distribusiKategori($period),
            'peringatan'          => $this->peringatan(),
            'periode'             => $period ? $this->periodInfo($period) : null,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/dashboard/attendance-trend?range=7|30
    // ─────────────────────────────────────────────────────────────────────────
    public function attendanceTrend(Request $request)
    {
        $range = in_array((int) $request->range, [7, 30]) ? (int) $request->range : 7;

        $from = Carbon::today('Asia/Jakarta')->subDays($range - 1)->toDateString();
        $to   = Carbon::today('Asia/Jakarta')->toDateString();

        // Satu query group-by tanggal+status — tidak ada N+1
        $rows = Attendance::select('tanggal', 'status', DB::raw('COUNT(*) as jumlah'))
            ->whereBetween('tanggal', [$from, $to])
            ->groupBy('tanggal', 'status')
            ->orderBy('tanggal')
            ->get();

        // Pivot per tanggal
        $byDate = [];
        foreach ($rows as $r) {
            $d = $r->tanggal instanceof \Carbon\Carbon
                ? $r->tanggal->toDateString()
                : (string) $r->tanggal;
            $byDate[$d] ??= ['tanggal' => $d, 'hadir' => 0, 'terlambat' => 0, 'tidak_hadir' => 0];
            if ($r->status === 'HADIR')    $byDate[$d]['hadir']       += $r->jumlah;
            if ($r->status === 'TERLAMBAT') $byDate[$d]['terlambat']  += $r->jumlah;
            if ($r->status === 'ABSENT')   $byDate[$d]['tidak_hadir'] += $r->jumlah;
        }

        // Isi hari tanpa data dengan 0
        $result = [];
        for ($i = 0; $i < $range; $i++) {
            $d = Carbon::today('Asia/Jakarta')->subDays($range - 1 - $i)->toDateString();
            $result[] = $byDate[$d] ?? ['tanggal' => $d, 'hadir' => 0, 'terlambat' => 0, 'tidak_hadir' => 0];
        }

        return response()->json($result);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/dashboard/recent-attendance?limit=10
    // ─────────────────────────────────────────────────────────────────────────
    public function recentAttendance(Request $request)
    {
        $limit = min((int) ($request->limit ?? 10), 50);

        $rows = Attendance::with('employee:id,nama,nip')
            ->select('id', 'employee_id', 'tanggal', 'jam_masuk', 'jam_keluar', 'status', 'metode', 'menit_terlambat')
            ->orderByDesc('tanggal')
            ->orderByDesc('jam_masuk')
            ->limit($limit)
            ->get();

        return response()->json($rows);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Kartu ringkasan: data hari ini (atau hari terakhir yang ada data).
     */
    private function kartu(): array
    {
        $totalAktif = Employee::where('status_aktif', true)->count();

        // Cari tanggal terakhir yang punya data attendance
        $lastDate = Attendance::max('tanggal');
        if (!$lastDate) {
            return [
                'total_karyawan_aktif' => $totalAktif,
                'tanggal_referensi'    => null,
                'tepat_waktu'          => 0,
                'terlambat'            => 0,
                'tidak_hadir'          => 0,
                'absen_fingerprint'    => 0,
            ];
        }

        // Satu query agregasi untuk tanggal tersebut
        $stats = Attendance::select(
                DB::raw("SUM(CASE WHEN status = 'HADIR' THEN 1 ELSE 0 END) as tepat_waktu"),
                DB::raw("SUM(CASE WHEN status = 'TERLAMBAT' THEN 1 ELSE 0 END) as terlambat"),
                DB::raw("SUM(CASE WHEN status = 'ABSENT' THEN 1 ELSE 0 END) as tidak_hadir"),
                DB::raw("SUM(CASE WHEN metode = 'fingerprint' AND status IN ('HADIR','TERLAMBAT') THEN 1 ELSE 0 END) as absen_fingerprint")
            )
            ->whereDate('tanggal', $lastDate)
            ->first();

        return [
            'total_karyawan_aktif' => $totalAktif,
            'tanggal_referensi'    => $lastDate,
            'tepat_waktu'          => (int) $stats->tepat_waktu,
            'terlambat'            => (int) $stats->terlambat,
            'tidak_hadir'          => (int) $stats->tidak_hadir,
            'absen_fingerprint'    => (int) $stats->absen_fingerprint,
        ];
    }

    /**
     * Rekap kehadiran untuk donut chart pada rentang periode terpilih (atau 30 hari terakhir).
     */
    private function rekapKehadiran(?EvaluationPeriod $period): array
    {
        $query = Attendance::select(
            'status',
            DB::raw('COUNT(*) as jumlah')
        )->groupBy('status');

        if ($period) {
            $query->whereBetween('tanggal', [
                $period->tanggal_mulai->toDateString(),
                $period->tanggal_selesai->toDateString(),
            ]);
        } else {
            $query->where('tanggal', '>=', Carbon::today('Asia/Jakarta')->subDays(29)->toDateString());
        }

        $rows = $query->pluck('jumlah', 'status');

        $tepat    = (int) ($rows['HADIR']    ?? 0);
        $terlambat = (int) ($rows['TERLAMBAT'] ?? 0);
        $absen    = (int) ($rows['ABSENT']   ?? 0);
        $total    = $tepat + $terlambat + $absen;

        return [
            'tepat_waktu'  => $tepat,
            'terlambat'    => $terlambat,
            'tidak_hadir'  => $absen,
            'total'        => $total,
        ];
    }

    /**
     * Rata-rata C1, C2, C3 (nilai mentah) dari hasil evaluasi pada periode terpilih.
     */
    private function rataRataKriteria(?EvaluationPeriod $period): array
    {
        $defaults = ['C1' => null, 'C2' => null, 'C3' => null];
        if (!$period) {
            return $defaults;
        }

        // Satu query join + group-by kode
        $rows = SpkEvaluationDetail::select(
                'spk_criteria.kode',
                DB::raw('AVG(spk_evaluation_details.nilai_mentah) as rata_rata')
            )
            ->join('spk_criteria', 'spk_criteria.id', '=', 'spk_evaluation_details.spk_criteria_id')
            ->join('spk_evaluations', 'spk_evaluations.id', '=', 'spk_evaluation_details.spk_evaluation_id')
            ->where('spk_evaluations.evaluation_period_id', $period->id)
            ->groupBy('spk_criteria.kode')
            ->pluck('rata_rata', 'kode');

        return [
            'C1' => isset($rows['C1']) ? round((float) $rows['C1'], 2) : null,
            'C2' => isset($rows['C2']) ? round((float) $rows['C2'], 2) : null,
            'C3' => isset($rows['C3']) ? round((float) $rows['C3'], 2) : null,
        ];
    }

    /**
     * Distribusi 4 kategori (termasuk yang 0) dari periode terpilih.
     */
    private function distribusiKategori(?EvaluationPeriod $period): array
    {
        $KATEGORI = ['positif', 'pertimbangan', 'perhatian', 'evaluasi_khusus'];

        if (!$period) {
            return collect($KATEGORI)->mapWithKeys(fn($k) => [$k => ['jumlah' => 0, 'persen' => 0.0]])->all();
        }

        $rows = DB::table('spk_evaluations')
            ->select('kategori', DB::raw('COUNT(*) as jumlah'))
            ->where('evaluation_period_id', $period->id)
            ->groupBy('kategori')
            ->pluck('jumlah', 'kategori');

        $total = $rows->sum();

        $result = [];
        foreach ($KATEGORI as $k) {
            $jumlah = (int) ($rows[$k] ?? 0);
            $result[$k] = [
                'jumlah' => $jumlah,
                'persen' => $total > 0 ? round($jumlah / $total * 100, 1) : 0.0,
            ];
        }

        return $result;
    }

    /**
     * Peringatan: absen tanpa alasan + device tidak aktif.
     * Kedua angka dihitung dalam satu query masing-masing.
     */
    private function peringatan(): array
    {
        $absenTanpaAlasan = Attendance::where('status', 'ABSENT')
            ->whereNull('absence_reason_id')
            ->count();

        $cutoff = Carbon::now('Asia/Jakarta')->subMinutes(self::DEVICE_ACTIVE_MINUTES);
        $perangkatTidakAktif = IotDevice::where(function ($q) use ($cutoff) {
            $q->whereNull('last_seen_at')
              ->orWhere('last_seen_at', '<', $cutoff);
        })->count();

        return [
            'absen_tanpa_alasan'    => $absenTanpaAlasan,
            'perangkat_tidak_aktif' => $perangkatTidakAktif,
        ];
    }

    /**
     * Info periode.
     */
    private function periodInfo(EvaluationPeriod $period): array
    {
        return [
            'id'               => $period->id,
            'tanggal_mulai'    => $period->tanggal_mulai ? $period->tanggal_mulai->toDateString() : null,
            'tanggal_selesai'  => $period->tanggal_selesai ? $period->tanggal_selesai->toDateString() : null,
            'status'           => $period->status,
            'status_evaluasi'  => $period->status_evaluasi,
            'grup_kontrak' => [
                'kontrak_mulai'   => $period->kontrak_mulai ? $period->kontrak_mulai->toDateString() : null,
                'kontrak_selesai' => $period->kontrak_selesai ? $period->kontrak_selesai->toDateString() : null,
            ],
        ];
    }
}
