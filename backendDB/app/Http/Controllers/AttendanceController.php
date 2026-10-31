<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Services\AttendanceService;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $query = Attendance::with(['employee', 'absenceReason']);

        if ($request->filled('date_from')) {
            $query->where('tanggal', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('tanggal', '<=', $request->date_to);
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('metode')) {
            $query->where('metode', $request->metode);
        }

        if ($request->filled('has_reason')) {
            if (filter_var($request->has_reason, FILTER_VALIDATE_BOOLEAN)) {
                $query->whereNotNull('absence_reason_id');
            } else {
                $query->whereNull('absence_reason_id');
            }
        }

        $attendances = $query->orderBy('tanggal', 'desc')->paginate($request->per_page ?? 15);

        return response()->json([
            'data' => $attendances->items(),
            'meta' => [
                'current_page' => $attendances->currentPage(),
                'from'         => $attendances->firstItem(),
                'last_page'    => $attendances->lastPage(),
                'per_page'     => $attendances->perPage(),
                'to'           => $attendances->lastItem(),
                'total'        => $attendances->total(),
            ],
        ]);

    }

    public function updateReason(Request $request, Attendance $attendance)
    {
        if ($attendance->status !== 'ABSENT') {
            return response()->json(['message' => 'Hanya status ABSENT yang dapat diubah alasannya'], 422);
        }

        $validated = $request->validate([
            'absence_reason_id' => ['required', 'exists:absence_reasons,id'],
        ]);

        $attendance->update([
            'absence_reason_id' => $validated['absence_reason_id'],
        ]);

        return response()->json($attendance);
    }

    public function unexplainedCount()
    {
        $count = Attendance::where('status', 'ABSENT')
            ->whereNull('absence_reason_id')
            ->count();

        return response()->json(['count' => $count]);
    }

    public function tap(Request $request, AttendanceService $attendanceService)
    {
        $validated = $request->validate([
            'device_id' => ['sometimes', 'nullable', 'string'],
            'metode' => ['required', 'in:RFID,FINGERPRINT'],
            'identifier' => ['required', 'string'],
            'timestamp' => ['required', 'date_format:Y-m-d\TH:i:s'],
        ]);

        $device = $request->attributes->get('device');

        $result = $attendanceService->processTap($validated, $device);

        if ($result['status'] === 'not_found') {
            return response()->json(['status' => 'not_found'], 404);
        }

        return response()->json($result);
    }
}
