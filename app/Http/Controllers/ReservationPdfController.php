<?php

namespace App\Http\Controllers;

use App\Actions\Settings\SettingsService;
use App\Models\Enums\ReservationArtifactKind;
use App\Models\Enums\ReservationArtifactStatus;
use App\Models\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\ReservationArtifact;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReservationPdfController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Reservation $reservation, SettingsService $settings): BinaryFileResponse
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
            $path = $this->generateAndStore($reservation, $settings);
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

    private function generateAndStore(Reservation $reservation, SettingsService $settings): string
    {
        $timezone = $settings->getString('timezone');
        $template = $settings->getString('pdf_template');
        $template = $template !== '' ? $template : 'default';

        $path = "reservations/{$reservation->id}/reservation.pdf";

        $pdf = Pdf::loadView('pdfs.reservation.default', [
            'reservation' => $reservation,
            'timezone' => $timezone,
            'template' => $template,
        ]);

        Storage::disk('local')->put($path, $pdf->output());

        $artifact = ReservationArtifact::query()->firstOrNew([
            'reservation_id' => $reservation->id,
            'kind' => ReservationArtifactKind::Pdf,
        ]);

        $artifact->attempts = ($artifact->attempts ?? 0) + 1;
        $artifact->last_attempt_at = now();
        $artifact->last_error = null;
        $artifact->status = ReservationArtifactStatus::Sent;
        $artifact->payload = array_merge(is_array($artifact->payload) ? $artifact->payload : [], [
            'path' => $path,
            'template' => $template,
        ]);
        $artifact->save();

        return $path;
    }
}
