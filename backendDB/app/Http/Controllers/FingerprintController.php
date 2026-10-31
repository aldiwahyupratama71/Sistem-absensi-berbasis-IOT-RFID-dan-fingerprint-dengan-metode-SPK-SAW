<?php

namespace App\Http\Controllers;

use App\Http\Resources\FingerprintResource;
use App\Models\Employee;
use App\Models\Fingerprint;
use App\Models\IotDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FingerprintController extends Controller
{
    /**
     * GET /api/employees/{employee}/fingerprints
     * Daftar fingerprint karyawan.
     */
    public function index(Employee $employee): JsonResponse
    {
        $fingerprints = $employee->fingerprints()->with('iotDevice')->get();

        return response()->json([
            'data' => FingerprintResource::collection($fingerprints),
        ]);
    }

    /**
     * POST /api/employees/{employee}/fingerprints
     * Tambah fingerprint karyawan.
     */
    public function store(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => ['required', 'integer', 'exists:iot_devices,id'],
            'finger_id' => ['required', 'string'],
        ], [
            'device_id.required' => 'Device ID wajib diisi.',
            'device_id.exists' => 'Device tidak valid.',
            'finger_id.required' => 'Finger ID wajib diisi.',
        ]);

        // Cek duplikat finger_id pada device yang sama
        $exists = Fingerprint::where('iot_device_id', $validated['device_id'])
            ->where('finger_id', $validated['finger_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Finger ID sudah digunakan di device ini.',
            ], 422);
        }

        $fingerprint = $employee->fingerprints()->create([
            'iot_device_id' => $validated['device_id'],
            'finger_id' => $validated['finger_id'],
        ]);

        return response()->json([
            'data' => new FingerprintResource($fingerprint->load('iotDevice')),
        ], 201);
    }

    /**
     * DELETE /api/employees/{employee}/fingerprints/{fingerprint}
     * Hapus pendaftaran fingerprint dari sistem.
     * Catatan: penghapusan dari modul AS608 di ESP32 harus dilakukan secara
     * fisik terpisah (lihat ASSUMPTIONS.md A-FP1).
     */
    public function destroy(Employee $employee, Fingerprint $fingerprint): JsonResponse
    {
        if ($fingerprint->employee_id !== $employee->id) {
            return response()->json(['message' => 'Fingerprint tidak ditemukan untuk karyawan ini.'], 404);
        }

        $fingerprint->delete();

        return response()->json([
            'message' => 'Data fingerprint berhasil dihapus dari sistem.',
        ]);
    }

    /**
     * POST /api/devices/{device}/fingerprints
     * Endpoint KHUSUS DEVICE (auth: X-API-KEY) untuk menautkan finger_id
     * yang baru selesai dienroll di modul AS608 ke karyawan di database.
     *
     * Lihat ASSUMPTIONS.md A-FP2 untuk detail flow firmware.
     */
    public function linkFromDevice(Request $request, IotDevice $device): JsonResponse
    {
        $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'finger_id'   => ['required', 'integer', 'min:1', 'max:127'],
        ], [
            'employee_id.required' => 'ID karyawan wajib diisi.',
            'employee_id.exists'   => 'Karyawan tidak ditemukan.',
            'finger_id.required'   => 'Finger ID wajib diisi.',
        ]);

        // Cek duplikat finger_id pada device yang sama
        $exists = Fingerprint::where('iot_device_id', $device->id)
            ->where('finger_id', $request->finger_id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => "Finger ID {$request->finger_id} sudah terdaftar di device ini.",
            ], 422);
        }

        $fingerprint = Fingerprint::create([
            'employee_id'    => $request->employee_id,
            'iot_device_id'  => $device->id,
            'finger_id'      => $request->finger_id,
        ]);

        return response()->json([
            'message' => 'Fingerprint berhasil ditautkan.',
            'data'    => new FingerprintResource($fingerprint->load('iotDevice')),
        ], 201);
    }
}
