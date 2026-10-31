<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeCardRequest;
use App\Http\Resources\EmployeeCardResource;
use App\Models\Employee;
use App\Models\EmployeeCard;
use Illuminate\Http\JsonResponse;

class EmployeeCardController extends Controller
{
    /**
     * GET /api/employees/{employee}/cards
     * Menampilkan daftar kartu RFID milik karyawan.
     */
    public function index(Employee $employee): JsonResponse
    {
        $cards = $employee->cards()->orderByDesc('created_at')->get();
        return response()->json([
            'data' => EmployeeCardResource::collection($cards)
        ]);
    }

    /**
     * POST /api/employees/{employee}/cards
     * Tambah kartu RFID ke karyawan.
     */
    public function store(StoreEmployeeCardRequest $request, Employee $employee): JsonResponse
    {
        if (! $employee->status_aktif) {
            return response()->json([
                'message' => 'Tidak dapat menambah kartu ke karyawan nonaktif.',
            ], 422);
        }

        $card = $employee->cards()->create([
            'uid_rfid' => $request->uid_rfid,
            'status'   => 'aktif',
        ]);

        return response()->json([
            'message' => 'Kartu RFID berhasil ditambahkan.',
            'data'    => new EmployeeCardResource($card),
        ], 201);
    }

    /**
     * PATCH /api/employees/{employee}/cards/{card}/status
     * Toggle status kartu RFID (aktif <-> nonaktif).
     */
    public function updateStatus(Employee $employee, EmployeeCard $card): JsonResponse
    {
        if ($card->employee_id !== $employee->id) {
            return response()->json(['message' => 'Kartu tidak ditemukan untuk karyawan ini.'], 404);
        }

        $newStatus = $card->status === 'aktif' ? 'nonaktif' : 'aktif';
        $card->update(['status' => $newStatus]);

        $label = $newStatus === 'aktif' ? 'diaktifkan' : 'dinonaktifkan';

        return response()->json([
            'message' => "Kartu RFID berhasil {$label}.",
            'data'    => new EmployeeCardResource($card->fresh()),
        ]);
    }
}
