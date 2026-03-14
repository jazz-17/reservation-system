<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Reservations\ReservationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\CancelReservationRequest;
use App\Models\Reservation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReservationHistoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/History');
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

        return to_route('admin.history.index')->with('success', 'Reserva cancelada.');
    }
}
