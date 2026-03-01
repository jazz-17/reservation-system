<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Audit\Audit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAllowListEntryRequest;
use App\Http\Requests\Admin\UpdateAllowListEntryRequest;
use App\Models\AllowListEntry;
use App\Models\ProfessionalSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AllowListController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $search = $request->query('search');
        $search = is_string($search) ? trim($search) : '';

        $entries = AllowListEntry::query()
            ->with('professionalSchool:id,name')
            ->when($search !== '', fn ($q) => $q->where('email', 'ilike', "%{$search}%"))
            ->orderBy('email')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/AllowList', [
            'entries' => $entries,
            'filters' => ['search' => $search],
            'count' => AllowListEntry::query()->count(),
            'schools' => ProfessionalSchool::query()
                ->where('active', true)
                ->whereHas('faculty', fn ($query) => $query->where('active', true))
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'base_year_min', 'base_year_max']),
        ]);
    }

    public function create(): InertiaResponse
    {
        return Inertia::render('admin/AllowListCreate', [
            'schools' => ProfessionalSchool::query()
                ->where('active', true)
                ->whereHas('faculty', fn ($query) => $query->where('active', true))
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'base_year_min', 'base_year_max']),
        ]);
    }

    public function store(StoreAllowListEntryRequest $request): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $email = Str::lower((string) $request->validated('email'));
        $schoolId = (int) $request->validated('professional_school_id');
        $studentCode = (string) $request->validated('student_code');
        $baseYear = $request->derivedBaseYear();

        $batchId = (string) Str::uuid();

        $entry = AllowListEntry::query()->updateOrCreate(
            ['email' => $email],
            [
                'student_code' => $studentCode,
                'professional_school_id' => $schoolId,
                'base_year' => $baseYear,
                'import_batch_id' => $batchId,
                'imported_by' => $user->id,
            ],
        );

        Audit::record('allow_list.entry_saved', actor: $user, subject: $entry, metadata: [
            'email' => $entry->email,
            'student_code' => $entry->student_code,
            'professional_school_id' => $entry->professional_school_id,
            'base_year' => $entry->base_year,
            'batch_id' => $batchId,
        ]);

        return back()->with('success', 'Correo agregado a la allow-list correctamente.');
    }

    public function update(UpdateAllowListEntryRequest $request, AllowListEntry $allowListEntry): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $allowListEntry->update([
            'email' => Str::lower((string) $request->validated('email')),
            'student_code' => (string) $request->validated('student_code'),
            'professional_school_id' => (int) $request->validated('professional_school_id'),
            'base_year' => $request->derivedBaseYear(),
            'imported_by' => $user->id,
        ]);

        Audit::record('allow_list.entry_updated', actor: $user, subject: $allowListEntry, metadata: [
            'email' => $allowListEntry->email,
            'student_code' => $allowListEntry->student_code,
            'professional_school_id' => $allowListEntry->professional_school_id,
            'base_year' => $allowListEntry->base_year,
        ]);

        return back()->with('success', 'Entrada actualizada correctamente.');
    }

    public function destroy(Request $request, AllowListEntry $allowListEntry): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $metadata = [
            'email' => $allowListEntry->email,
            'student_code' => $allowListEntry->student_code,
            'professional_school_id' => $allowListEntry->professional_school_id,
        ];

        $allowListEntry->delete();

        Audit::record('allow_list.entry_deleted', actor: $user, metadata: $metadata);

        return back()->with('success', 'Entrada eliminada de la allow-list.');
    }
}
