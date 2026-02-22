<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AllowList\AllowListImportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportAllowListRequest;
use App\Models\AllowListEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AllowListController extends Controller
{
    public function index(): InertiaResponse
    {
        return Inertia::render('admin/AllowList', [
            'count' => AllowListEntry::query()->count(),
        ]);
    }

    public function import(ImportAllowListRequest $request, AllowListImportService $importer): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $file = $request->file('file');
        if ($file === null) {
            abort(422);
        }

        $report = $importer->import(
            admin: $user,
            file: $file,
            mode: $request->validated('mode'),
        );

        return back()->with('import_report', $report)->with('success', 'Allow-list importada correctamente.');
    }

    public function template(): Response
    {
        $content = implode("\n", [
            'email,school_code,base',
            'alumno@unmsm.edu.pe,ep_sistemas,B22',
            '',
        ]);

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="allow-list-template.csv"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }
}
