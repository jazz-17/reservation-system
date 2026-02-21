<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AllowList\AllowListImportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportAllowListRequest;
use App\Models\AllowListEntry;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AllowListController extends Controller
{
    public function index(): Response
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
}
