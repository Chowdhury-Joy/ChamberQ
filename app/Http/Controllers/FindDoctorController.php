<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Services\DoctorDirectoryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FindDoctorController extends Controller
{
    public function index(Request $request, DoctorDirectoryService $directory): View
    {
        $query = trim((string) $request->query('q', ''));
        $specialty = trim((string) $request->query('specialty', ''));
        $specialty = $specialty !== '' ? $specialty : null;

        return view('patient.find', [
            'listings' => $directory->listings($query !== '' ? $query : null, $specialty),
            'query' => $query,
            'specialty' => $specialty,
            'specialties' => Doctor::practiceTypeOptions(),
        ]);
    }
}
