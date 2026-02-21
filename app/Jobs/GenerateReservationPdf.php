<?php

namespace App\Jobs;

use App\Actions\Settings\SettingsService;
use App\Models\Enums\ReservationArtifactKind;
use App\Models\Enums\ReservationArtifactStatus;
use App\Models\ReservationArtifact;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class GenerateReservationPdf implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $artifactId) {}

    /**
     * Execute the job.
     */
    public function handle(SettingsService $settings): void
    {
        $artifact = ReservationArtifact::query()
            ->with(['reservation.user'])
            ->find($this->artifactId);

        if ($artifact === null || $artifact->kind !== ReservationArtifactKind::Pdf) {
            return;
        }

        $artifact->forceFill([
            'status' => ReservationArtifactStatus::Pending,
            'attempts' => $artifact->attempts + 1,
            'last_attempt_at' => now(),
            'last_error' => null,
        ])->save();

        try {
            $timezone = $settings->getString('timezone');
            $template = (string) ($artifact->payload['template'] ?? 'default');

            $reservation = $artifact->reservation;
            if ($reservation === null) {
                throw new \RuntimeException('Reservation not found.');
            }

            $path = "reservations/{$reservation->id}/reservation.pdf";

            $pdf = Pdf::loadView('pdfs.reservation.default', [
                'reservation' => $reservation,
                'timezone' => $timezone,
                'template' => $template,
            ]);

            Storage::disk('local')->put($path, $pdf->output());

            $artifact->forceFill([
                'status' => ReservationArtifactStatus::Sent,
                'payload' => array_merge($artifact->payload ?? [], [
                    'path' => $path,
                    'template' => $template,
                ]),
            ])->save();
        } catch (Throwable $exception) {
            $artifact->forceFill([
                'status' => ReservationArtifactStatus::Failed,
                'last_error' => Str::limit($exception->getMessage(), 2000),
            ])->save();
        }
    }
}
