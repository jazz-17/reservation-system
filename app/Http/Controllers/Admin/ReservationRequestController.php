<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Reservations\ReservationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DecideReservationRequest;
use App\Http\Requests\Student\CancelReservationRequest;
use App\Models\Reservation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReservationRequestController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Requests');
    }

    public function approve(DecideReservationRequest $request, Reservation $reservation, ReservationService $service): RedirectResponse
    {
        $service->approve($request->user(), $reservation, $request->validated('reason'));

        return to_route('admin.requests.index')->with('success', 'Solicitud aprobada.');
    }

    public function reject(DecideReservationRequest $request, Reservation $reservation, ReservationService $service): RedirectResponse
    {
        $service->reject($request->user(), $reservation, $request->validated('reason'));

        return to_route('admin.requests.index')->with('success', 'Solicitud rechazada.');
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

        return to_route('admin.requests.index')->with('success', 'Reserva cancelada.');
    }
}
