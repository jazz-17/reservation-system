<?php

namespace App\Actions\Reservations;

use App\Models\Blackout;
use App\Models\Enums\ReservationStatus;
use App\Models\RecurringBlackout;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Spatie\Period\Boundaries;
use Spatie\Period\Period;
use Spatie\Period\Precision;

class AvailabilityService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function eventsForRange(string $start, string $end): array
    {
        $timezone = (string) config('app.timezone', 'America/Lima');

        $startLocal = CarbonImmutable::parse($start, $timezone);
        $endLocal = CarbonImmutable::parse($end, $timezone);

        $startUtc = $startLocal->setTimezone('UTC');
        $endUtc = $endLocal->setTimezone('UTC');

        $reservations = Reservation::query()
            ->approved()
            ->overlapping($startUtc, $endUtc)
            ->orderBy('starts_at')
            ->get(['starts_at', 'ends_at']);

        $pendingReservations = Reservation::query()
            ->where('status', ReservationStatus::Pending)
            ->overlapping($startUtc, $endUtc)
            ->orderBy('starts_at')
            ->get(['starts_at', 'ends_at']);

        $blackouts = Blackout::query()
            ->where('starts_at', '<', $endUtc)
            ->where('ends_at', '>', $startUtc)
            ->orderBy('starts_at')
            ->get(['starts_at', 'ends_at', 'reason']);

        $events = [];

        foreach ($reservations as $reservation) {
            $events[] = [
                'title' => 'Ocupado',
                'start' => $reservation->starts_at->setTimezone($timezone)->toIso8601String(),
                'end' => $reservation->ends_at->setTimezone($timezone)->toIso8601String(),
                'color' => '#f59e0b',
                'extendedProps' => [
                    'type' => 'reservation',
                ],
            ];
        }

        foreach ($pendingReservations as $pending) {
            $events[] = [
                'title' => 'Solicitado',
                'start' => $pending->starts_at->setTimezone($timezone)->toIso8601String(),
                'end' => $pending->ends_at->setTimezone($timezone)->toIso8601String(),
                'color' => '#3b82f6',
                'extendedProps' => [
                    'type' => 'pending',
                ],
            ];
        }

        foreach ($blackouts as $blackout) {
            $reason = is_string($blackout->reason) && $blackout->reason !== ''
                ? "Bloqueado - {$blackout->reason}"
                : 'Bloqueado';

            $events[] = [
                'title' => $reason,
                'start' => $blackout->starts_at->setTimezone($timezone)->toIso8601String(),
                'end' => $blackout->ends_at->setTimezone($timezone)->toIso8601String(),
                'display' => 'background',
                'color' => '#64748b',
                'extendedProps' => [
                    'type' => 'blackout',
                ],
            ];
        }

        foreach ($this->recurringBlackoutEventsForRange($startLocal, $endLocal, $timezone) as $event) {
            $events[] = $event;
        }

        usort($events, function (array $a, array $b): int {
            return strcmp((string) $a['start'], (string) $b['start']);
        });

        return $events;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recurringBlackoutEventsForRange(CarbonImmutable $startLocal, CarbonImmutable $endLocal, string $timezone): array
    {
        $rangeStart = $startLocal->startOfDay();
        $rangeEnd = $endLocal->subSecond()->startOfDay();

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
            ->orderBy('weekday')
            ->orderBy('starts_time')
            ->get(['weekday', 'starts_time', 'ends_time', 'starts_on', 'ends_on', 'reason']);

        if ($rules->isEmpty()) {
            return [];
        }

        $rulesByWeekday = $rules->groupBy('weekday');

        $requestedPeriod = Period::make($startLocal, $endLocal, Precision::MINUTE(), Boundaries::EXCLUDE_END());

        $events = [];

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

                if (! $requestedPeriod->overlapsWith($occurrencePeriod)) {
                    continue;
                }

                $reason = is_string($rule->reason) && $rule->reason !== ''
                    ? "Bloqueado - {$rule->reason}"
                    : 'Bloqueado';

                $events[] = [
                    'title' => $reason,
                    'start' => $occurrenceStart->toIso8601String(),
                    'end' => $occurrenceEnd->toIso8601String(),
                    'display' => 'background',
                    'color' => '#64748b',
                    'extendedProps' => [
                        'type' => 'blackout',
                    ],
                ];
            }
        }

        return $events;
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
}
