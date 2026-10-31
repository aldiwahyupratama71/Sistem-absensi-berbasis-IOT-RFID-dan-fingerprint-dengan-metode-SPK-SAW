<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContractController extends Controller
{
    public function index(Request $request)
    {
        $query = Contract::with('employee');

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $contracts = $query->orderBy('tanggal_mulai', 'desc')->paginate($request->per_page ?? 15);

        return response()->json([
            'data' => $contracts->items(),
            'meta' => [
                'current_page' => $contracts->currentPage(),
                'from'         => $contracts->firstItem(),
                'last_page'    => $contracts->lastPage(),
                'per_page'     => $contracts->perPage(),
                'to'           => $contracts->lastItem(),
                'total'        => $contracts->total(),
            ],
        ]);
    }

    public function groups()
    {
        $groups = Contract::selectRaw('tanggal_mulai as kontrak_mulai, tanggal_selesai as kontrak_selesai, COUNT(DISTINCT employee_id) as jumlah_karyawan')
            ->groupBy('tanggal_mulai', 'tanggal_selesai')
            ->orderBy('tanggal_mulai', 'desc')
            ->get();

        foreach ($groups as $group) {
            $hasPeriod = \App\Models\EvaluationPeriod::where('kontrak_mulai', $group->kontrak_mulai)
                ->where('kontrak_selesai', $group->kontrak_selesai)
                ->exists();
            
            $group->sudah_punya_periode = $hasPeriod;
            // Format to Y-m-d explicitly to avoid timezone shift
            $group->kontrak_mulai = \Carbon\Carbon::parse($group->kontrak_mulai)->format('Y-m-d');
            $group->kontrak_selesai = \Carbon\Carbon::parse($group->kontrak_selesai)->format('Y-m-d');
            $group->nama_grup = "Kontrak " . \Carbon\Carbon::parse($group->kontrak_mulai)->translatedFormat('d M Y') . " – " . \Carbon\Carbon::parse($group->kontrak_selesai)->translatedFormat('d M Y');
        }

        return response()->json($groups);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'tanggal_mulai' => ['required', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['required', 'date_format:Y-m-d', 'after:tanggal_mulai'],
            'status' => ['required', 'in:aktif,selesai,diperpanjang,diputus'],
        ]);

        if ($validated['status'] === 'aktif') {
            $overlap = Contract::where('employee_id', $validated['employee_id'])
                ->where('status', 'aktif')
                ->where(function ($query) use ($validated) {
                    $query->where('tanggal_mulai', '<=', $validated['tanggal_selesai'])
                          ->where('tanggal_selesai', '>=', $validated['tanggal_mulai']);
                })->exists();

            if ($overlap) {
                return response()->json(['message' => 'Terdapat kontrak aktif yang tumpang tindih untuk karyawan ini.'], 422);
            }
        }

        $contract = Contract::create($validated);
        return response()->json($contract, 201);
    }

    public function show(Contract $contract)
    {
        return response()->json($contract->load('employee'));
    }

    public function update(Request $request, Contract $contract)
    {
        $validated = $request->validate([
            'employee_id' => ['sometimes', 'required', 'exists:employees,id'],
            'tanggal_mulai' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['sometimes', 'required', 'date_format:Y-m-d', 'after:tanggal_mulai'],
            'status' => ['sometimes', 'required', 'in:aktif,selesai,diperpanjang,diputus'],
        ]);

        $employeeId = $validated['employee_id'] ?? $contract->employee_id;
        $tanggalMulai = $validated['tanggal_mulai'] ?? $contract->tanggal_mulai;
        $tanggalSelesai = $validated['tanggal_selesai'] ?? $contract->tanggal_selesai;
        $status = $validated['status'] ?? $contract->status;

        if ($status === 'aktif') {
            $overlap = Contract::where('employee_id', $employeeId)
                ->where('id', '!=', $contract->id)
                ->where('status', 'aktif')
                ->where(function ($query) use ($tanggalMulai, $tanggalSelesai) {
                    $query->where('tanggal_mulai', '<=', $tanggalSelesai)
                          ->where('tanggal_selesai', '>=', $tanggalMulai);
                })->exists();

            if ($overlap) {
                return response()->json(['message' => 'Terdapat kontrak aktif yang tumpang tindih untuk karyawan ini.'], 422);
            }
        }

        $contract->update($validated);
        return response()->json($contract);
    }

    public function destroy(Contract $contract)
    {
        $contract->delete();
        return response()->noContent();
    }
}
