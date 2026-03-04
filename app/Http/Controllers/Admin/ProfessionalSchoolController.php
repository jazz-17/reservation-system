<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProfessionalSchoolRequest;
use App\Http\Requests\Admin\UpdateProfessionalSchoolRequest;
use App\Models\ProfessionalSchool;
use Illuminate\Http\RedirectResponse;

class ProfessionalSchoolController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.faculties.index');
    }

    public function store(StoreProfessionalSchoolRequest $request): RedirectResponse
    {
        ProfessionalSchool::query()->create([
            'faculty_id' => $request->validated('faculty_id'),
            'code' => $request->validated('code'),
            'name' => $request->validated('name'),
            'base_year_min' => $request->validated('base_year_min'),
            'base_year_max' => $request->validated('base_year_max'),
            'active' => $request->boolean('active', true),
        ]);

        return back()->with('success', 'Escuela creada correctamente.');
    }

    public function update(UpdateProfessionalSchoolRequest $request, ProfessionalSchool $professionalSchool): RedirectResponse
    {
        $professionalSchool->forceFill([
            'faculty_id' => $request->validated('faculty_id'),
            'code' => $request->validated('code'),
            'name' => $request->validated('name'),
            'base_year_min' => $request->validated('base_year_min'),
            'base_year_max' => $request->validated('base_year_max'),
            'active' => $request->boolean('active', true),
        ])->save();

        return back()->with('success', 'Escuela actualizada correctamente.');
    }
}
