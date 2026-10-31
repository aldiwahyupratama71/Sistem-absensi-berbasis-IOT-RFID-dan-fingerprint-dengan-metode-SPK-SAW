<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIotDeviceRequest;
use App\Http\Requests\UpdateIotDeviceRequest;
use App\Http\Resources\IotDeviceResource;
use App\Models\IotDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class IotDeviceController extends Controller
{
    /**
     * GET /api/devices
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $devices = IotDevice::latest()->paginate($request->integer('per_page', 15));
        return IotDeviceResource::collection($devices);
    }

    /**
     * POST /api/devices
     * API key di-generate acak (48 char), disimpan sebagai SHA-256 hash.
     * Plaintext tampil HANYA SEKALI di response ini.
     */
    public function store(StoreIotDeviceRequest $request): JsonResponse
    {
        [$device, $plainKey] = $this->createDeviceWithKey($request->validated());

        return (new IotDeviceResource($device))
            ->additional(['api_key' => $plainKey])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/devices/{device}
     */
    public function show(IotDevice $device): IotDeviceResource
    {
        return new IotDeviceResource($device);
    }

    /**
     * PUT/PATCH /api/devices/{device}
     */
    public function update(UpdateIotDeviceRequest $request, IotDevice $device): JsonResponse
    {
        $device->update($request->validated());

        return response()->json([
            'message' => 'Device berhasil diperbarui.',
            'data'    => new IotDeviceResource($device->fresh()),
        ]);
    }

    /**
     * DELETE /api/devices/{device}
     * Menghapus device permanent (tidak ada histori penting di tabel ini).
     * Attendance records yang sudah ada tetap tersimpan (nullOnDelete).
     */
    public function destroy(IotDevice $device): JsonResponse
    {
        $device->delete();

        return response()->json(['message' => 'Device berhasil dihapus.']);
    }

    /**
     * POST /api/devices/{device}/regenerate-key
     * Generate ulang API key. Plaintext tampil SEKALI, key lama langsung tidak berlaku.
     */
    public function regenerateKey(Request $request, IotDevice $device): JsonResponse
    {
        $plainKey = $this->generateAndStoreKey($device);

        return (new IotDeviceResource($device->fresh()))
            ->additional(['api_key' => $plainKey])
            ->response();
    }

    /**
     * POST /api/devices/heartbeat
     * Endpoint untuk heartbeat perangkat IoT. Last seen at di-update oleh middleware.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function createDeviceWithKey(array $validated): array
    {
        $plainKey = Str::random(48); // min 40 char sesuai requirement

        $device = IotDevice::create([
            'nama'           => $validated['nama'],
            'lokasi'         => $validated['lokasi'] ?? null,
            'api_key_hash'   => hash('sha256', $plainKey),
            'api_key_prefix' => substr($plainKey, 0, 8),
        ]);

        return [$device, $plainKey];
    }

    private function generateAndStoreKey(IotDevice $device): string
    {
        $plainKey = Str::random(48);

        $device->update([
            'api_key_hash'   => hash('sha256', $plainKey),
            'api_key_prefix' => substr($plainKey, 0, 8),
        ]);

        return $plainKey;
    }
}
