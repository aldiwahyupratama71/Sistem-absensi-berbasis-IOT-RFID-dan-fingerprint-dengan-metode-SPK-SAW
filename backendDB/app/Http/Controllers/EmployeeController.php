<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;


class EmployeeController extends Controller
{
    /**
     * GET /api/employees
     * Daftar karyawan dengan pencarian nama/NIP/departemen + paginate.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Employee::with(['cards', 'fingerprints'])
            ->when($request->search, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('nama', 'like', "%{$search}%")
                      ->orWhere('nip', 'like', "%{$search}%")
                      ->orWhere('departemen', 'like', "%{$search}%");
                });
            })
            ->when($request->status_aktif !== null, function ($q) use ($request) {
                $q->where('status_aktif', filter_var($request->status_aktif, FILTER_VALIDATE_BOOLEAN));
            })
            ->latest();

        return EmployeeResource::collection(
            $query->paginate($request->integer('per_page', 15))
        );
    }

    /**
     * POST /api/employees
     */
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = Employee::create([
            'nip'          => $request->nip,
            'nama'         => $request->nama,
            'departemen'   => $request->departemen,
            'jabatan'      => $request->jabatan,
            'status'       => 'aktif',
            'status_aktif' => true,
        ]);

        return response()->json([
            'message' => 'Karyawan berhasil ditambahkan.',
            'data'    => new EmployeeResource($employee),
        ], 201);
    }

    /**
     * GET /api/employees/{employee}
     */
    public function show(Employee $employee): EmployeeResource
    {
        $employee->load(['cards', 'fingerprints.iotDevice']);
        return new EmployeeResource($employee);
    }

    /**
     * PUT/PATCH /api/employees/{employee}
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $data = $request->validated();

        // Sinkronkan status (enum) dengan status_aktif (boolean)
        if (array_key_exists('status_aktif', $data)) {
            $data['status'] = $data['status_aktif'] ? 'aktif' : 'nonaktif';
        }

        $employee->update($data);

        return response()->json([
            'message' => 'Data karyawan berhasil diperbarui.',
            'data'    => new EmployeeResource($employee->fresh(['cards', 'fingerprints'])),
        ]);
    }

    /**
     * DELETE /api/employees/{employee}
     * DELETE /api/employees/{employee}?force=true  ← hapus SEMUA data (purge)
     *
     * Normal: hanya boleh jika tidak punya riwayat.
     * Force : hapus semua data karyawan ini secara permanen tanpa syarat.
     */
    public function destroy(Request $request, Employee $employee): JsonResponse
    {
        if ($request->boolean('force')) {
            return $this->forcePurge($employee);
        }

        // ── Normal delete ───────────────────────────────────────────────────

        // (a) Tolak jika karyawan punya riwayat
        $punyaRiwayat = $employee->attendance()->exists()
            || $employee->contracts()->exists()
            || $employee->spkEvaluations()->exists();

        if ($punyaRiwayat) {
            return response()->json([
                'message' => 'Karyawan memiliki riwayat absensi/kontrak/evaluasi dan tidak bisa dihapus permanen. Nonaktifkan lewat menu Edit.',
            ], 422);
        }

        // (b) Tolak jika masih punya kartu RFID aktif
        if ($employee->cards()->where('status', 'aktif')->exists()) {
            return response()->json([
                'message' => 'Nonaktifkan semua kartu RFID karyawan ini terlebih dahulu sebelum menghapus.',
            ], 422);
        }

        // (c) Hapus permanen dalam satu transaksi DB
        DB::transaction(function () use ($employee) {
            $employee->cards()->delete();
            $employee->fingerprints()->delete();
            $employee->delete();
        });

        return response()->json([
            'message' => 'Karyawan berhasil dihapus secara permanen.',
        ]);
    }

    /**
     * Hapus SEMUA data karyawan secara permanen (force purge).
     * Urutan hapus sesuai FK constraint:
     *  1. spk_evaluations → CASCADE ke spk_evaluation_details & hrd_decisions
     *  2. attendance (RESTRICT — harus eksplisit)
     *  3. contracts  (RESTRICT — harus eksplisit)
     *  4. employee_cards & fingerprints (CASCADE, eksplisit untuk kejelasan)
     *  5. employees
     */
    private function forcePurge(Employee $employee): JsonResponse
    {
        // Hitung jumlah data sebelum dihapus (untuk laporan di response)
        $counts = [
            'absensi'    => $employee->attendance()->count(),
            'kontrak'    => $employee->contracts()->count(),
            'evaluasi'   => $employee->spkEvaluations()->count(),
            'kartu_rfid' => $employee->cards()->count(),
            'sidik_jari' => $employee->fingerprints()->count(),
        ];

        DB::transaction(function () use ($employee) {
            // 1. Evaluasi SPK → CASCADE ke detail & keputusan HRD
            $employee->spkEvaluations()->delete();
            // 2. Absensi
            $employee->attendance()->delete();
            // 3. Kontrak
            $employee->contracts()->delete();
            // 4. Kartu RFID & sidik jari
            $employee->cards()->delete();
            $employee->fingerprints()->delete();
            // 5. Karyawan itu sendiri
            $employee->delete();
        });

        return response()->json([
            'message' => 'Semua data karyawan berhasil dihapus secara permanen.',
            'deleted' => $counts,
        ]);
    }



    /**
     * GET /api/employees/{employee}/evaluation-history
     */
    public function evaluationHistory(Employee $employee): JsonResponse
    {
        $history = $employee->spkEvaluations()
            ->with('evaluationPeriod')
            ->orderBy('calculated_at', 'asc') // Urut berdasarkan waktu hitung untuk melihat tren (atau tanggal_mulai periode)
            ->get()
            ->map(function ($eval) {
                return [
                    'id' => $eval->id,
                    'periode_mulai' => $eval->evaluationPeriod->tanggal_mulai,
                    'periode_selesai' => $eval->evaluationPeriod->tanggal_selesai,
                    'nilai_akhir' => $eval->nilai_akhir,
                    'kategori' => $eval->kategori,
                    'calculated_at' => $eval->calculated_at,
                ];
            });

        return response()->json($history);
    }
}
