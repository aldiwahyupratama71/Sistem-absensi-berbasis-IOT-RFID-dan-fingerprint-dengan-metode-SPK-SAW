<?php

namespace App\Http\Controllers;

use App\Models\AbsenceReason;
use Illuminate\Http\Request;

class AbsenceReasonController extends Controller
{
    public function index()
    {
        return response()->json(AbsenceReason::orderBy('nama', 'asc')->get());
    }
}
