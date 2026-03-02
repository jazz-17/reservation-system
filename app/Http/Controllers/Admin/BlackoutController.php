<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Audit\Audit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBlackoutRequest;
use App\Http\Requests\Admin\StoreRecurringBlackoutRequest;
use App\Models\Blackout;
use App\Models\RecurringBlackout;
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
            'recurring_blackouts' => RecurringBlackout::query()->latest('created_at')->limit(200)->get(),
        ]);
    }

    public function store(StoreBlackoutRequest $request): RedirectResponse
    {
        $admin = $request->user();
        if ($admin === null) {
            abort(401);
        }

        $timezone = (string) config('app.timezone', 'America/Lima');

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

    public function storeRecurring(StoreRecurringBlackoutRequest $request): RedirectResponse
    {
        $admin = $request->user();
        if ($admin === null) {
            abort(401);
        }

        $validated = $request->validated();

        $recurringBlackout = RecurringBlackout::query()->create([
            'weekday' => (int) $validated['weekday'],
            'starts_time' => $validated['starts_time'],
            'ends_time' => $validated['ends_time'],
            'starts_on' => $validated['starts_on'] ?? null,
            'ends_on' => $validated['ends_on'] ?? null,
            'reason' => $validated['reason'] ?? null,
            'created_by' => $admin->id,
        ]);

        Audit::record('recurring_blackout.created', actor: $admin, subject: $recurringBlackout, metadata: [
            'weekday' => $recurringBlackout->weekday,
            'starts_time' => $recurringBlackout->starts_time,
            'ends_time' => $recurringBlackout->ends_time,
            'starts_on' => $recurringBlackout->starts_on?->toDateString(),
            'ends_on' => $recurringBlackout->ends_on?->toDateString(),
            'reason' => $recurringBlackout->reason,
        ]);

        return back()->with('success', 'Bloqueo recurrente creado.');
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

    public function destroyRecurring(RecurringBlackout $recurringBlackout): RedirectResponse
    {
        $admin = request()->user();
        if ($admin === null) {
            abort(401);
        }

        $id = $recurringBlackout->id;
        $recurringBlackout->delete();

        Audit::record('recurring_blackout.deleted', actor: $admin, subject: null, metadata: [
            'recurring_blackout_id' => $id,
        ]);

        return back()->with('success', 'Bloqueo recurrente eliminado.');
    }
}
