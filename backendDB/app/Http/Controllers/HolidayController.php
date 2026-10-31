<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HolidayController extends Controller
{
    public function index()
    {
        return response()->json(Holiday::orderBy('tanggal', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tanggal' => ['required', 'date', 'unique:holidays,tanggal'],
            'keterangan' => ['required', 'string', 'max:255'],
        ]);

        $holiday = Holiday::create($validated);

        return response()->json($holiday, 201);
    }

    public function show(Holiday $holiday)
    {
        return response()->json($holiday);
    }

    public function update(Request $request, Holiday $holiday)
    {
        $validated = $request->validate([
            'tanggal' => ['required', 'date', Rule::unique('holidays')->ignore($holiday->id)],
            'keterangan' => ['required', 'string', 'max:255'],
        ]);

        $holiday->update($validated);

        return response()->json($holiday);
    }

    public function destroy(Holiday $holiday)
    {
        $holiday->delete();

        return response()->noContent();
    }
}
