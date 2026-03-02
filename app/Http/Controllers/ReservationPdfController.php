<?php

namespace App\Http\Controllers;

use App\Models\Enums\ReservationArtifactKind;
use App\Models\Enums\ReservationArtifactStatus;
use App\Models\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\ReservationArtifact;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReservationPdfController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Reservation $reservation): BinaryFileResponse
    {
        if ($request->user() === null) {
            abort(401);
        }

        if ($reservation->status !== ReservationStatus::Approved) {
            abort(404);
        }

        $reservation->loadMissing(['user', 'professionalSchool']);

        $path = $this->existingPdfPath($reservation);
        if ($path === null) {
            $path = $this->generateAndStore($reservation);
        }

        $filename = "reserva-{$reservation->id}.pdf";

        return response()->download(
            Storage::disk('local')->path($path),
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }

    private function existingPdfPath(Reservation $reservation): ?string
    {
        $artifact = ReservationArtifact::query()
            ->where('reservation_id', $reservation->id)
            ->where('kind', ReservationArtifactKind::Pdf)
            ->where('status', ReservationArtifactStatus::Sent)
            ->first();

        $payload = is_array($artifact?->payload) ? $artifact->payload : [];
        $path = $payload['path'] ?? null;

        if (! is_string($path) || $path === '') {
            return null;
        }

        if (! Storage::disk('local')->exists($path)) {
            return null;
        }

        return $path;
    }

    private function generateAndStore(Reservation $reservation): string
    {
        $path = "reservations/{$reservation->id}/reservation.pdf";

        $pdf = Pdf::loadView('pdfs.reservation.default', [
            'reservation' => $reservation,
        ]);

        Storage::disk('local')->put($path, $pdf->output());

        $artifact = ReservationArtifact::query()->firstOrNew([
            'reservation_id' => $reservation->id,
            'kind' => ReservationArtifactKind::Pdf,
        ]);

        $artifact->attempts = ($artifact->attempts ?? 0) + 1;
        $artifact->last_attempt_at = CarbonImmutable::now('UTC');
        $artifact->last_error = null;
        $artifact->status = ReservationArtifactStatus::Sent;
        $artifact->payload = array_merge(is_array($artifact->payload) ? $artifact->payload : [], [
            'path' => $path,
        ]);
        $artifact->save();

        return $path;
    }
}
