<?php

namespace App\Actions\Reservations;

use App\Actions\Audit\Audit;
use App\Actions\Settings\SettingsService;
use App\Jobs\GenerateReservationPdf;
use App\Jobs\SendReservationEmail;
use App\Models\Enums\BookingMode;
use App\Models\Enums\ReservationArtifactKind;
use App\Models\Enums\ReservationArtifactStatus;
use App\Models\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\ReservationArtifact;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly ReservationRulesService $rules,
    ) {}

    public function createPending(User $user, CarbonImmutable $startsAtUtc, ?CarbonImmutable $endsAtUtc): Reservation
    {
        $mode = BookingMode::from($this->settings->getString('booking_mode'));

        $computedEndsAtUtc = match ($mode) {
            BookingMode::FixedDuration => $startsAtUtc->addMinutes($this->settings->getInt('slot_duration_minutes')),
            default => $endsAtUtc,
        };

        if ($computedEndsAtUtc === null) {
            throw ValidationException::withMessages([
                'ends_at' => 'Debes indicar una hora de fin.',
            ]);
        }

        $this->rules->validateForCreation($user, $startsAtUtc, $computedEndsAtUtc);

        try {
            return DB::transaction(function () use ($user, $startsAtUtc, $computedEndsAtUtc): Reservation {
                $reservation = Reservation::query()->create([
                    'user_id' => $user->id,
                    'status' => ReservationStatus::Pending,
                    'starts_at' => $startsAtUtc,
                    'ends_at' => $computedEndsAtUtc,
                    'professional_school' => (string) $user->professional_school,
                    'base' => (string) $user->base,
                ]);

                Audit::record('reservation.created', actor: $user, subject: $reservation, metadata: [
                    'status' => 'pending',
                ]);

                return $reservation;
            });
        } catch (QueryException $exception) {
            throw ValidationException::withMessages([
                'starts_at' => 'Horario no disponible.',
            ]);
        }
    }

    public function cancel(User $actor, Reservation $reservation, ?string $reason = null): Reservation
    {
        $this->rules->validateCancellation($actor, $reservation);

        $updated = DB::transaction(function () use ($actor, $reservation, $reason): Reservation {
            $reservation->forceFill([
                'status' => ReservationStatus::Cancelled,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ])->save();

            Audit::record('reservation.cancelled', actor: $actor, subject: $reservation, metadata: [
                'reason' => $reason,
            ]);

            return $reservation->refresh();
        });

        $this->enqueueEmails($updated, event: 'cancelled');

        return $updated;
    }

    public function approve(User $admin, Reservation $reservation, ?string $reason = null): Reservation
    {
        if ($reservation->status !== ReservationStatus::Pending) {
            throw ValidationException::withMessages([
                'reservation' => 'Solo se pueden aprobar solicitudes pendientes.',
            ]);
        }

        $this->rules->validateForApproval($reservation);

        $updated = DB::transaction(function () use ($admin, $reservation, $reason): Reservation {
            $reservation->forceFill([
                'status' => ReservationStatus::Approved,
                'decided_by' => $admin->id,
                'decided_at' => now(),
                'decision_reason' => $reason,
            ])->save();

            Audit::record('reservation.approved', actor: $admin, subject: $reservation, metadata: [
                'reason' => $reason,
            ]);

            return $reservation->refresh();
        });

        $this->enqueuePdf($updated);
        $this->enqueueEmails($updated, event: 'approved');

        return $updated;
    }

    public function reject(User $admin, Reservation $reservation, ?string $reason = null): Reservation
    {
        if ($reservation->status !== ReservationStatus::Pending) {
            throw ValidationException::withMessages([
                'reservation' => 'Solo se pueden rechazar solicitudes pendientes.',
            ]);
        }

        $updated = DB::transaction(function () use ($admin, $reservation, $reason): Reservation {
            $reservation->forceFill([
                'status' => ReservationStatus::Rejected,
                'decided_by' => $admin->id,
                'decided_at' => now(),
                'decision_reason' => $reason,
            ])->save();

            Audit::record('reservation.rejected', actor: $admin, subject: $reservation, metadata: [
                'reason' => $reason,
            ]);

            return $reservation->refresh();
        });

        $this->enqueueEmails($updated, event: 'rejected');

        return $updated;
    }

    public function expirePending(?CarbonImmutable $nowUtc = null): int
    {
        $hours = $this->settings->getInt('pending_expiration_hours');
        $nowUtc ??= CarbonImmutable::now('UTC');

        $cutoff = $nowUtc->subHours($hours);

        $reservations = Reservation::query()
            ->where('status', ReservationStatus::Pending)
            ->where('created_at', '<=', $cutoff)
            ->get();

        if ($reservations->isEmpty()) {
            return 0;
        }

        DB::transaction(function () use ($reservations, $nowUtc): void {
            foreach ($reservations as $reservation) {
                $reservation->forceFill([
                    'status' => ReservationStatus::Rejected,
                    'decided_by' => null,
                    'decided_at' => $nowUtc,
                    'decision_reason' => 'Expirada por falta de aprobación.',
                ])->save();

                Audit::record('reservation.expired', actor: null, subject: $reservation, metadata: [
                    'status' => 'rejected',
                ]);
            }
        });

        foreach ($reservations as $reservation) {
            $this->enqueueEmails($reservation, event: 'expired');
        }

        return $reservations->count();
    }

    private function enqueuePdf(Reservation $reservation): void
    {
        $artifact = ReservationArtifact::query()->updateOrCreate(
            [
                'reservation_id' => $reservation->id,
                'kind' => ReservationArtifactKind::Pdf,
            ],
            [
                'status' => ReservationArtifactStatus::Pending,
                'attempts' => 0,
                'payload' => [
                    'template' => $this->settings->getString('pdf_template'),
                ],
            ],
        );

        DB::afterCommit(function () use ($artifact): void {
            GenerateReservationPdf::dispatch($artifact->id);
        });
    }

    private function enqueueEmails(Reservation $reservation, string $event): void
    {
        $notifyAdmin = $this->settings->get('notify_admin_emails');
        $adminRecipients = is_array($notifyAdmin) ? $notifyAdmin : ['to' => []];

        $adminTo = array_values(array_filter($adminRecipients['to'] ?? []));
        $adminCc = array_values(array_filter($adminRecipients['cc'] ?? []));
        $adminBcc = array_values(array_filter($adminRecipients['bcc'] ?? []));

        if (count($adminTo) > 0) {
            $adminArtifact = ReservationArtifact::query()->updateOrCreate(
                [
                    'reservation_id' => $reservation->id,
                    'kind' => ReservationArtifactKind::EmailAdmin,
                ],
                [
                    'status' => ReservationArtifactStatus::Pending,
                    'attempts' => 0,
                    'payload' => [
                        'event' => $event,
                        'to' => $adminTo,
                        'cc' => $adminCc,
                        'bcc' => $adminBcc,
                    ],
                ],
            );

            DB::afterCommit(function () use ($adminArtifact): void {
                SendReservationEmail::dispatch($adminArtifact->id);
            });
        }

        $shouldNotifyStudent = $this->settings->getBool('notify_student_on_approval');
        if (! $shouldNotifyStudent) {
            return;
        }

        $reservation->loadMissing('user');
        $studentEmail = $reservation->user?->email;
        if (! is_string($studentEmail) || $studentEmail === '') {
            return;
        }

        $studentArtifact = ReservationArtifact::query()->updateOrCreate(
            [
                'reservation_id' => $reservation->id,
                'kind' => ReservationArtifactKind::EmailStudent,
            ],
            [
                'status' => ReservationArtifactStatus::Pending,
                'attempts' => 0,
                'payload' => [
                    'event' => $event,
                    'to' => [$studentEmail],
                    'cc' => [],
                    'bcc' => [],
                ],
            ],
        );

        DB::afterCommit(function () use ($studentArtifact): void {
            SendReservationEmail::dispatch($studentArtifact->id);
        });
    }
}
