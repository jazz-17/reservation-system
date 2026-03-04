<?php

namespace App\Http\Controllers\Student;

use App\Actions\Reservations\ReservationService;
use App\Actions\Settings\SettingsService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\CancelReservationRequest;
use App\Http\Requests\Student\StoreReservationRequest;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReservationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('reservations/Index');
    }

    public function create(SettingsService $settings): Response
    {
        return Inertia::render('reservations/Create', [
            'opening_hours' => $settings->get('opening_hours'),
            'min_duration_minutes' => $settings->getInt('min_duration_minutes'),
            'max_duration_minutes' => $settings->getInt('max_duration_minutes'),
            'lead_time_min_hours' => $settings->getInt('lead_time_min_hours'),
            'lead_time_max_days' => $settings->getInt('lead_time_max_days'),
        ]);
    }

    public function store(StoreReservationRequest $request, ReservationService $service): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $timezone = (string) config('app.timezone', 'America/Lima');

        $startsAtUtc = CarbonImmutable::parse($request->validated('starts_at'), $timezone)->setTimezone('UTC');
        $endsAtUtc = CarbonImmutable::parse($request->validated('ends_at'), $timezone)->setTimezone('UTC');

        $service->createPending($user, $startsAtUtc, $endsAtUtc);

        return to_route('reservations.index')->with('success', 'Solicitud registrada. Queda pendiente de aprobación.');
    }

    public function cancel(
        CancelReservationRequest $request,
        Reservation $reservation,
        ReservationService $service,
    ): RedirectResponse {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $service->cancel($user, $reservation, $request->validated('reason'));

        return to_route('reservations.index')->with('success', 'Reserva cancelada.');
    }
}
