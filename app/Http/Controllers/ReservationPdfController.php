<?php

namespace App\Http\Controllers;

use App\Models\Enums\ReservationStatus;
use App\Models\Reservation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReservationPdfController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Reservation $reservation): Response
    {
        if ($request->user() === null) {
            abort(401);
        }

        if ($reservation->status !== ReservationStatus::Approved) {
            abort(404);
        }

        $reservation->loadMissing(['user', 'professionalSchool']);

        $filename = "reserva-{$reservation->id}.pdf";

        return Pdf::loadView('pdfs.reservation.default', [
            'reservation' => $reservation,
        ])->download($filename);
    }
}
