<?php

namespace App\Actions\Reservations;

use App\Actions\Settings\SettingsService;
use App\Models\Blackout;
use App\Models\Enums\BookingMode;
use App\Models\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

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
        $mode = BookingMode::from($this->settings->getString('booking_mode'));

        $startsAtLocal = $startsAtUtc->setTimezone($timezone);
        $endsAtLocal = $endsAtUtc->setTimezone($timezone);

        $this->validateLeadTime($startsAtLocal);
        $this->validateOpeningHours($startsAtLocal, $endsAtLocal);
        $this->validateModeConstraints($mode, $startsAtLocal, $endsAtLocal);
        $this->validateBlackouts($startsAtUtc, $endsAtUtc);
        $this->validateUserActiveLimit($user);
        $this->validateWeeklyQuota($user, $startsAtLocal);
        $this->validateConflicts($startsAtUtc, $endsAtUtc);
    }

    public function validateForApproval(Reservation $reservation): void
    {
        $timezone = $this->settings->getString('timezone');

        $this->validateBlackouts(
            CarbonImmutable::parse($reservation->starts_at)->setTimezone('UTC'),
            CarbonImmutable::parse($reservation->ends_at)->setTimezone('UTC'),
        );

        $startsAtLocal = CarbonImmutable::parse($reservation->starts_at)->setTimezone($timezone);
        $endsAtLocal = CarbonImmutable::parse($reservation->ends_at)->setTimezone($timezone);

        $this->validateOpeningHours($startsAtLocal, $endsAtLocal);

        $this->validateConflicts(
            CarbonImmutable::parse($reservation->starts_at)->setTimezone('UTC'),
            CarbonImmutable::parse($reservation->ends_at)->setTimezone('UTC'),
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

        if ($startsAtLocal->lessThan($openAt) || $endsAtLocal->greaterThan($closeAt)) {
            throw ValidationException::withMessages([
                'starts_at' => 'La fecha/hora está fuera del horario de atención.',
            ]);
        }
    }

    private function validateModeConstraints(BookingMode $mode, CarbonImmutable $startsAtLocal, CarbonImmutable $endsAtLocal): void
    {
        $stepMinutes = $this->settings->getInt('slot_step_minutes');

        $alignsToStep = function (CarbonImmutable $dateTime) use ($stepMinutes): bool {
            if ($dateTime->second !== 0) {
                return false;
            }

            $minutes = $dateTime->hour * 60 + $dateTime->minute;

            return $minutes % $stepMinutes === 0;
        };

        if (! $alignsToStep($startsAtLocal)) {
            throw ValidationException::withMessages([
                'starts_at' => 'La hora de inicio no es válida.',
            ]);
        }

        if ($mode === BookingMode::FixedDuration) {
            $duration = $this->settings->getInt('slot_duration_minutes');

            if ($startsAtLocal->addMinutes($duration)->notEqualTo($endsAtLocal)) {
                throw ValidationException::withMessages([
                    'ends_at' => 'La duración seleccionada no es válida.',
                ]);
            }
        }

        if ($mode === BookingMode::VariableDuration) {
            if (! $alignsToStep($endsAtLocal)) {
                throw ValidationException::withMessages([
                    'ends_at' => 'La hora de fin no es válida.',
                ]);
            }

            $durationMinutes = $startsAtLocal->diffInMinutes($endsAtLocal);
            $min = $this->settings->getInt('min_duration_minutes');
            $max = $this->settings->getInt('max_duration_minutes');

            if ($durationMinutes < $min || $durationMinutes > $max) {
                throw ValidationException::withMessages([
                    'ends_at' => 'La duración seleccionada no es válida.',
                ]);
            }
        }

        if ($mode === BookingMode::PredefinedBlocks) {
            $blocksByDay = $this->settings->get('predefined_blocks');

            if (! is_array($blocksByDay)) {
                throw ValidationException::withMessages([
                    'starts_at' => 'No hay bloques predefinidos configurados.',
                ]);
            }

            $weekday = strtolower($startsAtLocal->format('D'));
            $blocks = $blocksByDay[$weekday] ?? [];

            $matchesBlock = false;
            foreach ($blocks as $block) {
                if (! is_array($block) || ! isset($block['start'], $block['end'])) {
                    continue;
                }

                $blockStart = CarbonImmutable::parse($startsAtLocal->format('Y-m-d').' '.$block['start'], $startsAtLocal->getTimezone());
                $blockEnd = CarbonImmutable::parse($startsAtLocal->format('Y-m-d').' '.$block['end'], $startsAtLocal->getTimezone());

                if ($blockStart->equalTo($startsAtLocal) && $blockEnd->equalTo($endsAtLocal)) {
                    $matchesBlock = true;
                    break;
                }
            }

            if (! $matchesBlock) {
                throw ValidationException::withMessages([
                    'starts_at' => 'El bloque seleccionado no es válido.',
                ]);
            }
        }
    }

    private function validateBlackouts(CarbonImmutable $startsAtUtc, CarbonImmutable $endsAtUtc): void
    {
        $overlaps = Blackout::query()
            ->where('starts_at', '<', $endsAtUtc)
            ->where('ends_at', '>', $startsAtUtc)
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'starts_at' => 'La fecha/hora está bloqueada por mantenimiento o feriado.',
            ]);
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

        $weekStartLocal = $startsAtLocal->startOfWeek(CarbonInterface::MONDAY);
        $weekEndLocal = $weekStartLocal->addWeek();

        $weekStartUtc = $weekStartLocal->setTimezone('UTC');
        $weekEndUtc = $weekEndLocal->setTimezone('UTC');

        $count = Reservation::query()
            ->blocking()
            ->where('professional_school', $user->professional_school)
            ->where('base', $user->base)
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
            ->blocking()
            ->overlapping($startsAtUtc, $endsAtUtc);

        if ($ignoreReservationId !== null) {
            $query->whereKeyNot($ignoreReservationId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'starts_at' => 'Horario no disponible.',
            ]);
        }
    }
}

