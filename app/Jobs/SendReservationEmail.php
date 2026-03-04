<?php

namespace App\Jobs;

use App\Mail\MailDeliveryState;
use App\Mail\ReservationStatusMail;
use App\Models\Enums\ReservationArtifactKind;
use App\Models\Enums\ReservationArtifactStatus;
use App\Models\ReservationArtifact;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class SendReservationEmail implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $artifactId) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $artifact = ReservationArtifact::query()
            ->with(['reservation.user', 'reservation.professionalSchool.faculty'])
            ->find($this->artifactId);

        if ($artifact === null || ! in_array($artifact->kind, [ReservationArtifactKind::EmailAdmin, ReservationArtifactKind::EmailStudent], true)) {
            return;
        }

        $artifact->forceFill([
            'status' => ReservationArtifactStatus::Pending,
            'attempts' => $artifact->attempts + 1,
            'last_attempt_at' => CarbonImmutable::now('UTC'),
            'last_error' => null,
        ])->save();

        $payload = is_array($artifact->payload) ? $artifact->payload : [];

        $event = (string) ($payload['event'] ?? 'updated');
        $to = array_values(array_filter($payload['to'] ?? []));
        $cc = array_values(array_filter($payload['cc'] ?? []));
        $bcc = array_values(array_filter($payload['bcc'] ?? []));

        try {
            if (count($to) === 0) {
                throw new \RuntimeException('Missing recipients.');
            }

            $reservation = $artifact->reservation;
            if ($reservation === null) {
                throw new \RuntimeException('Reservation not found.');
            }

            MailDeliveryState::reset();

            $recipientKind = $artifact->kind === ReservationArtifactKind::EmailAdmin ? 'admin' : 'student';

            Mail::to($to)
                ->cc($cc)
                ->bcc($bcc)
                ->send(new ReservationStatusMail(
                    reservation: $reservation,
                    event: $event,
                    recipientKind: $recipientKind,
                ));

            if (MailDeliveryState::wasSuppressed()) {
                $artifact->forceFill([
                    'status' => ReservationArtifactStatus::Skipped,
                ])->save();

                return;
            }

            $artifact->forceFill([
                'status' => ReservationArtifactStatus::Sent,
            ])->save();
        } catch (Throwable $exception) {
            $artifact->forceFill([
                'status' => ReservationArtifactStatus::Failed,
                'last_error' => Str::limit($exception->getMessage(), 2000),
            ])->save();
        }
    }
}
