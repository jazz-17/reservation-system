<?php

namespace App\Actions\Reservations;

use App\Actions\Settings\SettingsService;
use App\Models\Blackout;
use App\Models\Enums\ReservationStatus;
use App\Models\RecurringBlackout;
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

        $timezone = $this->timezone();

        $startsAtLocal = $startsAtUtc->setTimezone($timezone);
        $endsAtLocal = $endsAtUtc->setTimezone($timezone);

        $this->validateDuration($startsAtUtc, $endsAtUtc);
        $this->validateLeadTime($startsAtLocal);
        $this->validateOpeningHours($startsAtLocal, $endsAtLocal);
        $this->validateBlackouts($startsAtUtc, $endsAtUtc);
        $this->validateRecurringBlackouts($startsAtUtc, $endsAtUtc);
        $this->validateUserActiveLimit($user);
        $this->validateWeeklyQuota($user, $startsAtLocal);
        $this->validateConflicts($startsAtUtc, $endsAtUtc);
    }

    public function validateForApproval(Reservation $reservation): void
    {
        $timezone = $this->timezone();

        $startsAtUtc = CarbonImmutable::parse($reservation->starts_at)->setTimezone('UTC');
        $endsAtUtc = CarbonImmutable::parse($reservation->ends_at)->setTimezone('UTC');

        $this->validateDuration($startsAtUtc, $endsAtUtc);
        $this->validateBlackouts($startsAtUtc, $endsAtUtc);
        $this->validateRecurringBlackouts($startsAtUtc, $endsAtUtc);

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

        if ($actor->id !== $reservation->user_id && ! $actor->can('reservations.cancel.any')) {
            throw ValidationException::withMessages([
                'reservation' => 'No tienes permisos para cancelar esta reserva.',
            ]);
        }

        $nowUtc = CarbonImmutable::now('UTC');

        if ($reservation->ends_at->lessThanOrEqualTo($nowUtc)) {
            throw ValidationException::withMessages([
                'reservation' => 'No se puede cancelar una reserva finalizada.',
            ]);
        }
    }

    private function validateLeadTime(CarbonImmutable $startsAtLocal): void
    {
        $timezone = $this->timezone();
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

    private function validateRecurringBlackouts(CarbonImmutable $startsAtUtc, CarbonImmutable $endsAtUtc): void
    {
        $timezone = $this->timezone();

        $startsAtLocal = $startsAtUtc->setTimezone($timezone);
        $endsAtLocal = $endsAtUtc->setTimezone($timezone);

        $rangeStart = $startsAtLocal->startOfDay();
        $rangeEnd = $endsAtLocal->subSecond()->startOfDay();

        $weekdays = [];
        for ($cursor = $rangeStart; $cursor->lessThanOrEqualTo($rangeEnd); $cursor = $cursor->addDay()) {
            $weekdays[] = $cursor->dayOfWeek;
        }

        $weekdays = array_values(array_unique($weekdays));

        $rangeStartDate = $rangeStart->toDateString();
        $rangeEndDate = $rangeEnd->toDateString();

        $rules = RecurringBlackout::query()
            ->whereIn('weekday', $weekdays)
            ->where(function ($query) use ($rangeEndDate) {
                $query->whereNull('starts_on')->orWhere('starts_on', '<=', $rangeEndDate);
            })
            ->where(function ($query) use ($rangeStartDate) {
                $query->whereNull('ends_on')->orWhere('ends_on', '>=', $rangeStartDate);
            })
            ->get(['weekday', 'starts_time', 'ends_time', 'starts_on', 'ends_on']);

        if ($rules->isEmpty()) {
            return;
        }

        $rulesByWeekday = $rules->groupBy('weekday');

        $requestedPeriod = Period::make($startsAtLocal, $endsAtLocal, Precision::MINUTE(), Boundaries::EXCLUDE_END());

        for ($cursor = $rangeStart; $cursor->lessThanOrEqualTo($rangeEnd); $cursor = $cursor->addDay()) {
            $weekdayRules = $rulesByWeekday->get($cursor->dayOfWeek);
            if ($weekdayRules === null) {
                continue;
            }

            $dateString = $cursor->toDateString();

            foreach ($weekdayRules as $rule) {
                if (! $this->recurringRuleIsActiveOnDate($rule, $dateString)) {
                    continue;
                }

                $occurrenceStart = CarbonImmutable::parse($dateString.' '.$rule->starts_time, $timezone);
                $occurrenceEnd = CarbonImmutable::parse($dateString.' '.$rule->ends_time, $timezone);

                $occurrencePeriod = Period::make($occurrenceStart, $occurrenceEnd, Precision::MINUTE(), Boundaries::EXCLUDE_END());

                if ($requestedPeriod->overlapsWith($occurrencePeriod)) {
                    throw ValidationException::withMessages([
                        'starts_at' => 'La fecha/hora está bloqueada por mantenimiento o feriado.',
                    ]);
                }
            }
        }
    }

    private function recurringRuleIsActiveOnDate(RecurringBlackout $rule, string $dateString): bool
    {
        if ($rule->starts_on !== null && $dateString < $rule->starts_on->toDateString()) {
            return false;
        }

        if ($rule->ends_on !== null && $dateString > $rule->ends_on->toDateString()) {
            return false;
        }

        return true;
    }

    private function validateUserActiveLimit(User $user): void
    {
        $maxActive = $this->settings->getInt('max_active_reservations_per_user');

        if ($maxActive <= 0) {
            return;
        }

        $activeCount = Reservation::query()
            ->where('user_id', $user->id)
            ->active(CarbonImmutable::now('UTC'))
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

    private function timezone(): string
    {
        return (string) config('app.timezone', 'America/Lima');
    }
}
