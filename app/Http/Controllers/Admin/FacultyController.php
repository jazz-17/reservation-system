<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFacultyRequest;
use App\Http\Requests\Admin\UpdateFacultyRequest;
use App\Models\Faculty;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FacultyController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Faculties', [
            'faculties' => Faculty::query()
                ->orderBy('name')
                ->get(['id', 'name', 'active']),
        ]);
    }

    public function store(StoreFacultyRequest $request): RedirectResponse
    {
        Faculty::query()->create([
            'name' => $request->validated('name'),
            'active' => $request->boolean('active', true),
        ]);

        return back()->with('success', 'Facultad creada correctamente.');
    }

    public function update(UpdateFacultyRequest $request, Faculty $faculty): RedirectResponse
    {
        $faculty->forceFill([
            'name' => $request->validated('name'),
            'active' => $request->boolean('active', true),
        ])->save();

        return back()->with('success', 'Facultad actualizada correctamente.');
    }
}
