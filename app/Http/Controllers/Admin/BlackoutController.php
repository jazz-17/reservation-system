<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Audit\Audit;
use App\Actions\Settings\SettingsService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBlackoutRequest;
use App\Models\Blackout;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BlackoutController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Blackouts', [
            'blackouts' => Blackout::query()->latest('starts_at')->limit(200)->get(),
        ]);
    }

    public function store(StoreBlackoutRequest $request, SettingsService $settings): RedirectResponse
    {
        $admin = $request->user();
        if ($admin === null) {
            abort(401);
        }

        $timezone = $settings->getString('timezone');

        $startsAtUtc = CarbonImmutable::parse($request->validated('starts_at'), $timezone)->setTimezone('UTC');
        $endsAtUtc = CarbonImmutable::parse($request->validated('ends_at'), $timezone)->setTimezone('UTC');

        $blackout = Blackout::query()->create([
            'starts_at' => $startsAtUtc,
            'ends_at' => $endsAtUtc,
            'reason' => $request->validated('reason'),
            'created_by' => $admin->id,
        ]);

        Audit::record('blackout.created', actor: $admin, subject: $blackout, metadata: [
            'reason' => $blackout->reason,
        ]);

        return back()->with('success', 'Bloqueo creado.');
    }

    public function destroy(Blackout $blackout): RedirectResponse
    {
        $admin = request()->user();
        if ($admin === null) {
            abort(401);
        }

        $blackout->delete();

        Audit::record('blackout.deleted', actor: $admin, subject: null, metadata: [
            'blackout_id' => $blackout->id,
        ]);

        return back()->with('success', 'Bloqueo eliminado.');
    }
}
