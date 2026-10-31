<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\EvaluationPeriod;
use App\Models\SpkEvaluation;
use App\Services\SpkCalculationService;
use Illuminate\Http\Request;

class EvaluationPeriodController extends Controller
{
    protected SpkCalculationService $calcService;

    public function __construct(SpkCalculationService $calcService)
    {
        $this->calcService = $calcService;
    }

    /**
     * GET /api/evaluation-periods
     * Daftar periode evaluasi tanpa eager-load spk_evaluations penuh.
     * jumlah_anggota dan jumlah_terhitung dihitung via appends model.
     */
    public function index(Request $request)
    {
        $query = EvaluationPeriod::query();

        if ($request->filled('kontrak_mulai')) {
            $query->where('kontrak_mulai', $request->kontrak_mulai);
        }

        if ($request->filled('kontrak_selesai')) {
            $query->where('kontrak_selesai', $request->kontrak_selesai);
        }

        $periods = $query->orderBy('tanggal_mulai', 'desc')
                        ->paginate($request->per_page ?? 15);

        return response()->json([
            'data' => $periods->items(),
            'meta' => [
                'current_page' => $periods->currentPage(),
                'from'         => $periods->firstItem(),
                'last_page'    => $periods->lastPage(),
                'per_page'     => $periods->perPage(),
                'to'           => $periods->lastItem(),
                'total'        => $periods->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kontrak_mulai'   => ['required', 'date_format:Y-m-d'],
            'kontrak_selesai' => ['required', 'date_format:Y-m-d', 'after_or_equal:kontrak_mulai'],
            'tanggal_mulai'   => ['required', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
        ]);

        // Validasi: grup harus punya minimal 1 kontrak
        $count = Contract::where('tanggal_mulai', $validated['kontrak_mulai'])
            ->where('tanggal_selesai', $validated['kontrak_selesai'])
            ->count();
            
        if ($count === 0) {
            return response()->json(['message' => 'Tidak ada kontrak yang sesuai dengan tanggal grup ini.'], 422);
        }

        if ($validated['tanggal_mulai'] < $validated['kontrak_mulai'] ||
            $validated['tanggal_selesai'] > $validated['kontrak_selesai']) {
            return response()->json(['message' => 'Periode evaluasi harus berada di dalam rentang waktu kontrak grup.'], 422);
        }

        $overlap = EvaluationPeriod::where('kontrak_mulai', $validated['kontrak_mulai'])
            ->where('kontrak_selesai', $validated['kontrak_selesai'])
            ->exists();

        if ($overlap) {
            return response()->json(['message' => 'Grup kontrak ini sudah memiliki periode evaluasi.'], 422);
        }

        $period = EvaluationPeriod::create([
            ...$validated,
            'status' => 'berjalan',
        ]);
        return response()->json($period, 201);
    }

    public function show(EvaluationPeriod $evaluationPeriod)
    {
        return response()->json($evaluationPeriod);
    }

    public function update(Request $request, EvaluationPeriod $evaluationPeriod)
    {
        if ($evaluationPeriod->spkEvaluations()->exists() && !$request->boolean('force')) {
            return response()->json(['message' => 'Periode ini sudah memiliki hasil evaluasi. Gunakan parameter force=true untuk tetap mengubah.'], 422);
        }

        $validated = $request->validate([
            'tanggal_mulai'   => ['sometimes', 'required', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['sometimes', 'required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'status'          => ['sometimes', 'required', 'in:berjalan,selesai'],
        ]);

        $tanggalMulai = $validated['tanggal_mulai'] ?? $evaluationPeriod->tanggal_mulai;
        $tanggalSelesai = $validated['tanggal_selesai'] ?? $evaluationPeriod->tanggal_selesai;

        if ($tanggalMulai < $evaluationPeriod->kontrak_mulai->format('Y-m-d') || 
            $tanggalSelesai > $evaluationPeriod->kontrak_selesai->format('Y-m-d')) {
            return response()->json(['message' => 'Periode evaluasi harus berada di dalam rentang waktu kontrak grup.'], 422);
        }

        $evaluationPeriod->update($validated);
        return response()->json($evaluationPeriod);
    }

    public function destroy(Request $request, EvaluationPeriod $evaluationPeriod)
    {
        if ($evaluationPeriod->spkEvaluations()->exists() && !$request->boolean('force')) {
            return response()->json(['message' => 'Periode ini sudah memiliki hasil evaluasi. Gunakan parameter force=true untuk tetap menghapus.'], 422);
        }

        $evaluationPeriod->delete();
        return response()->noContent();
    }

    /**
     * GET /api/evaluation-periods/{id}/members
     * Daftar anggota grup kontrak beserta status evaluasi masing-masing.
     * Anggota = karyawan dengan kontrak yang kontrak_mulai & kontrak_selesai-nya
     * sama dengan periode evaluasi.
     */
    public function members(EvaluationPeriod $evaluationPeriod)
    {
        // Ambil semua kontrak dalam grup ini beserta relasi employee
        $contracts = Contract::with('employee')
            ->where('tanggal_mulai', $evaluationPeriod->kontrak_mulai)
            ->where('tanggal_selesai', $evaluationPeriod->kontrak_selesai)
            ->get();

        // Ambil semua evaluasi yang sudah dihitung untuk periode ini, key by employee_id
        $evaluations = SpkEvaluation::where('evaluation_period_id', $evaluationPeriod->id)
            ->get()
            ->keyBy('employee_id');

        $members = $contracts->map(function ($contract) use ($evaluations) {
            $employee = $contract->employee;
            $eval = $evaluations->get($contract->employee_id);

            return [
                'employee_id'     => $contract->employee_id,
                'contract_id'     => $contract->id,
                'nip'             => $employee?->nip,
                'nama'            => $employee?->nama,
                'departemen'      => $employee?->departemen,
                'jabatan'         => $employee?->jabatan,
                'sudah_dihitung'  => !is_null($eval),
                'nilai_akhir'     => $eval?->nilai_akhir,
                'kategori'        => $eval?->kategori,
                'spk_evaluation_id' => $eval?->id,
            ];
        });

        return response()->json([
            'evaluation_period_id' => $evaluationPeriod->id,
            'kontrak_mulai'        => $evaluationPeriod->kontrak_mulai->format('Y-m-d'),
            'kontrak_selesai'      => $evaluationPeriod->kontrak_selesai->format('Y-m-d'),
            'total'                => $members->count(),
            'data'                 => $members->values(),
        ]);
    }

    public function calculate(EvaluationPeriod $evaluationPeriod)
    {
        $result = $this->calcService->calculateGroup($evaluationPeriod);

        return response()->json([
            'message' => 'Kalkulasi berhasil',
            'summary' => $result
        ]);
    }

    public function results(Request $request, EvaluationPeriod $evaluationPeriod)
    {
        $query = SpkEvaluation::with(['employee', 'details.spkCriteria'])
            ->where('evaluation_period_id', $evaluationPeriod->id);

        if ($request->filled('kategori')) {
            $query->where('kategori', $request->kategori);
        }

        if ($request->filled('search')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('nama', 'like', "%{$request->search}%");
            });
        }

        $query->orderBy('nilai_akhir', 'desc');

        $results = $query->paginate($request->per_page ?? 15);

        $formatted = $results->through(function ($eval) {
            $c1 = $eval->details->firstWhere('spkCriteria.kode', 'C1');
            $c2 = $eval->details->firstWhere('spkCriteria.kode', 'C2');
            $c3 = $eval->details->firstWhere('spkCriteria.kode', 'C3');

            return [
                'id'          => $eval->id,
                'employee_id' => $eval->employee_id,
                'nama'        => $eval->employee->nama,
                'departemen'  => $eval->employee->departemen ?? 'N/A',
                'C1'          => $c1 ? $c1->nilai_mentah : null,
                'C2'          => $c2 ? $c2->nilai_mentah : null,
                'C3'          => $c3 ? $c3->nilai_mentah : null,
                'nilai_akhir' => $eval->nilai_akhir,
                'kategori'    => $eval->kategori,
                'calculated_at' => $eval->calculated_at,
            ];
        });

        return response()->json([
            'data' => $formatted->items(),
            'meta' => [
                'current_page' => $formatted->currentPage(),
                'from'         => $formatted->firstItem(),
                'last_page'    => $formatted->lastPage(),
                'per_page'     => $formatted->perPage(),
                'to'           => $formatted->lastItem(),
                'total'        => $formatted->total(),
            ],
        ]);
    }
}
