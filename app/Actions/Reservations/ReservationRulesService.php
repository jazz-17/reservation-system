<?php

namespace App\Actions\Reservations;

use App\Actions\Settings\SettingsService;
use App\Models\Blackout;
use App\Models\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;
use Spatie\Period\Boundaries;
use Spatie\Period\Period;
use Spatie\Period\Precision;

class ReservationRulesService
{
    public function __construct(private readonly SettingsService $settings) {}

    public function validateForCreation(User $user, CarbonImmutable $startsAtUtc, CarbonImmutable $endsAtUtc): void
    {
        if ($endsAtUtc->lessThanOrEqualTo($startsAtUtc)) {
            throw ValidationException::withMessages([
                'ends_at' => 'La hora de fin debe ser posterior a la hora de inicio.',
            ]);
        }

        $timezone = $this->settings->getString('timezone');

        $startsAtLocal = $startsAtUtc->setTimezone($timezone);
        $endsAtLocal = $endsAtUtc->setTimezone($timezone);

        $this->validateDuration($startsAtUtc, $endsAtUtc);
        $this->validateLeadTime($startsAtLocal);
        $this->validateOpeningHours($startsAtLocal, $endsAtLocal);
        $this->validateBlackouts($startsAtUtc, $endsAtUtc);
        $this->validateUserActiveLimit($user);
        $this->validateWeeklyQuota($user, $startsAtLocal);
        $this->validateConflicts($startsAtUtc, $endsAtUtc);
    }

    public function validateForApproval(Reservation $reservation): void
    {
        $timezone = $this->settings->getString('timezone');

        $startsAtUtc = CarbonImmutable::parse($reservation->starts_at)->setTimezone('UTC');
        $endsAtUtc = CarbonImmutable::parse($reservation->ends_at)->setTimezone('UTC');

        $this->validateDuration($startsAtUtc, $endsAtUtc);
        $this->validateBlackouts($startsAtUtc, $endsAtUtc);

        $startsAtLocal = $startsAtUtc->setTimezone($timezone);
        $endsAtLocal = $endsAtUtc->setTimezone($timezone);

        $this->validateOpeningHours($startsAtLocal, $endsAtLocal);

        $this->validateConflicts(
            $startsAtUtc,
            $endsAtUtc,
            ignoreReservationId: $reservation->id,
        );
    }

    public function validateCancellation(User $actor, Reservation $reservation): void
    {
        if (! in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Approved], true)) {
            throw ValidationException::withMessages([
                'reservation' => 'No se puede cancelar esta reserva.',
            ]);
        }

        if ($actor->id !== $reservation->user_id && ! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'reservation' => 'No tienes permisos para cancelar esta reserva.',
            ]);
        }

        $timezone = $this->settings->getString('timezone');
        $cutoffHours = $this->settings->getInt('cancel_cutoff_hours');

        $startsAtLocal = CarbonImmutable::parse($reservation->starts_at)->setTimezone($timezone);
        $nowLocal = CarbonImmutable::now($timezone);

        if ($nowLocal->greaterThan($startsAtLocal->subHours($cutoffHours))) {
            throw ValidationException::withMessages([
                'reservation' => 'Ya no es posible cancelar esta reserva (fuera del tiempo límite).',
            ]);
        }
    }

    private function validateLeadTime(CarbonImmutable $startsAtLocal): void
    {
        $timezone = $this->settings->getString('timezone');
        $nowLocal = CarbonImmutable::now($timezone);

        $minHours = $this->settings->getInt('lead_time_min_hours');
        $maxDays = $this->settings->getInt('lead_time_max_days');

        if ($startsAtLocal->lessThan($nowLocal->addHours($minHours))) {
            throw ValidationException::withMessages([
                'starts_at' => 'Debes reservar con mayor anticipación.',
            ]);
        }

        if ($startsAtLocal->greaterThan($nowLocal->addDays($maxDays))) {
            throw ValidationException::withMessages([
                'starts_at' => 'La fecha supera la anticipación máxima permitida.',
            ]);
        }
    }

    private function validateOpeningHours(CarbonImmutable $startsAtLocal, CarbonImmutable $endsAtLocal): void
    {
        $openingHours = $this->settings->get('opening_hours');

        if (! is_array($openingHours)) {
            return;
        }

        $weekday = strtolower($startsAtLocal->format('D'));
        $day = $openingHours[$weekday] ?? null;

        if (! is_array($day) || ! isset($day['open'], $day['close'])) {
            throw ValidationException::withMessages([
                'starts_at' => 'No hay horario de atención configurado para ese día.',
            ]);
        }

        $openAt = CarbonImmutable::parse($startsAtLocal->format('Y-m-d').' '.$day['open'], $startsAtLocal->getTimezone());
        $closeAt = CarbonImmutable::parse($startsAtLocal->format('Y-m-d').' '.$day['close'], $startsAtLocal->getTimezone());

        $openingPeriod = Period::make($openAt, $closeAt, Precision::MINUTE(), Boundaries::EXCLUDE_END());
        $requestedPeriod = Period::make($startsAtLocal, $endsAtLocal, Precision::MINUTE(), Boundaries::EXCLUDE_END());

        if (! $openingPeriod->contains($requestedPeriod)) {
            throw ValidationException::withMessages([
                'starts_at' => 'La fecha/hora está fuera del horario de atención.',
            ]);
        }
    }

    private function validateDuration(CarbonImmutable $startsAtUtc, CarbonImmutable $endsAtUtc): void
    {
        $durationMinutes = $startsAtUtc->diffInMinutes($endsAtUtc);
        $min = $this->settings->getInt('min_duration_minutes');
        $max = $this->settings->getInt('max_duration_minutes');

        if ($durationMinutes < $min || $durationMinutes > $max) {
            throw ValidationException::withMessages([
                'ends_at' => 'La duración seleccionada no es válida.',
            ]);
        }
    }

    private function validateBlackouts(CarbonImmutable $startsAtUtc, CarbonImmutable $endsAtUtc): void
    {
        $candidates = Blackout::query()
            ->where('starts_at', '<', $endsAtUtc)
            ->where('ends_at', '>', $startsAtUtc)
            ->get(['starts_at', 'ends_at']);

        if ($candidates->isEmpty()) {
            return;
        }

        $requestedPeriod = Period::make($startsAtUtc, $endsAtUtc, Precision::MINUTE(), Boundaries::EXCLUDE_END());

        foreach ($candidates as $blackout) {
            $blackoutPeriod = Period::make(
                $blackout->starts_at->setTimezone('UTC'),
                $blackout->ends_at->setTimezone('UTC'),
                Precision::MINUTE(),
                Boundaries::EXCLUDE_END(),
            );

            if ($requestedPeriod->overlapsWith($blackoutPeriod)) {
                throw ValidationException::withMessages([
                    'starts_at' => 'La fecha/hora está bloqueada por mantenimiento o feriado.',
                ]);
            }
        }
    }

    private function validateUserActiveLimit(User $user): void
    {
        $maxActive = $this->settings->getInt('max_active_reservations_per_user');

        if ($maxActive <= 0) {
            return;
        }

        $activeCount = Reservation::query()
            ->blocking()
            ->where('user_id', $user->id)
            ->count();

        if ($activeCount >= $maxActive) {
            throw ValidationException::withMessages([
                'reservation' => 'Ya alcanzaste el máximo de reservas activas.',
            ]);
        }
    }

    private function validateWeeklyQuota(User $user, CarbonImmutable $startsAtLocal): void
    {
        $limit = $this->settings->getInt('weekly_quota_per_school_base');

        if ($limit <= 0) {
            return;
        }

        if ($user->professional_school_id === null || $user->base_year === null) {
            return;
        }

        $weekStartLocal = $startsAtLocal->startOfWeek(CarbonInterface::MONDAY);
        $weekEndLocal = $weekStartLocal->addWeek();

        $weekStartUtc = $weekStartLocal->setTimezone('UTC');
        $weekEndUtc = $weekEndLocal->setTimezone('UTC');

        $count = Reservation::query()
            ->blocking()
            ->where('professional_school_id', $user->professional_school_id)
            ->where('base_year', $user->base_year)
            ->where('starts_at', '>=', $weekStartUtc)
            ->where('starts_at', '<', $weekEndUtc)
            ->count();

        if ($count >= $limit) {
            throw ValidationException::withMessages([
                'reservation' => 'La cuota semanal para tu grupo (Escuela/Base) está completa.',
            ]);
        }
    }

    private function validateConflicts(CarbonImmutable $startsAtUtc, CarbonImmutable $endsAtUtc, ?int $ignoreReservationId = null): void
    {
        $query = Reservation::query()
            ->approved()
            ->overlapping($startsAtUtc, $endsAtUtc);

        if ($ignoreReservationId !== null) {
            $query->whereKeyNot($ignoreReservationId);
        }

        $candidates = $query->get(['starts_at', 'ends_at']);

        if ($candidates->isEmpty()) {
            return;
        }

        $requestedPeriod = Period::make($startsAtUtc, $endsAtUtc, Precision::MINUTE(), Boundaries::EXCLUDE_END());

        foreach ($candidates as $reservation) {
            $reservationPeriod = Period::make(
                $reservation->starts_at->setTimezone('UTC'),
                $reservation->ends_at->setTimezone('UTC'),
                Precision::MINUTE(),
                Boundaries::EXCLUDE_END(),
            );

            if ($requestedPeriod->overlapsWith($reservationPeriod)) {
                throw ValidationException::withMessages([
                    'starts_at' => 'Horario no disponible.',
                ]);
            }
        }
    }
}
