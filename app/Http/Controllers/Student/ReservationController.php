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

    public function create(): Response
    {
        return Inertia::render('reservations/Create');
    }

    public function store(StoreReservationRequest $request, ReservationService $service, SettingsService $settings): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $timezone = $settings->getString('timezone');

        $startsAtUtc = CarbonImmutable::parse($request->validated('starts_at'), $timezone)->setTimezone('UTC');
        $endsAtUtc = $request->validated('ends_at') !== null
            ? CarbonImmutable::parse($request->validated('ends_at'), $timezone)->setTimezone('UTC')
            : null;

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
