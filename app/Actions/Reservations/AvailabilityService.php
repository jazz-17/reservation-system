<?php

namespace App\Actions\Reservations;

use App\Actions\Settings\SettingsService;
use App\Models\Blackout;
use App\Models\Enums\BookingMode;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AvailabilityService
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * @return array<string, mixed>
     */
    public function availabilityForRange(string $fromDate, string $toDate): array
    {
        $timezone = $this->settings->getString('timezone');
        $mode = BookingMode::from($this->settings->getString('booking_mode'));

        $fromLocal = CarbonImmutable::parse($fromDate, $timezone)->startOfDay();
        $toLocal = CarbonImmutable::parse($toDate, $timezone)->endOfDay();

        $fromUtc = $fromLocal->setTimezone('UTC');
        $toUtc = $toLocal->setTimezone('UTC');

        $busy = Reservation::query()
            ->blocking()
            ->overlapping($fromUtc, $toUtc)
            ->get(['starts_at', 'ends_at']);

        $blackouts = Blackout::query()
            ->where('starts_at', '<', $toUtc)
            ->where('ends_at', '>', $fromUtc)
            ->get(['starts_at', 'ends_at', 'reason']);

        $busyByDate = $this->groupByLocalDate($busy, $timezone);
        $blackoutsByDate = $this->groupByLocalDate($blackouts, $timezone);

        $openingHours = $this->settings->get('opening_hours');

        $days = [];
        for ($cursor = $fromLocal; $cursor->lessThanOrEqualTo($toLocal); $cursor = $cursor->addDay()) {
            $weekday = strtolower($cursor->format('D'));
            $dayHours = is_array($openingHours) ? ($openingHours[$weekday] ?? null) : null;

            $open = is_array($dayHours) ? (string) ($dayHours['open'] ?? '08:00') : '08:00';
            $close = is_array($dayHours) ? (string) ($dayHours['close'] ?? '22:00') : '22:00';

            $date = $cursor->format('Y-m-d');
            $dayBusy = $busyByDate[$date] ?? [];
            $dayBlackouts = $blackoutsByDate[$date] ?? [];

            $days[] = [
                'date' => $date,
                'open' => $open,
                'close' => $close,
                'busy' => $dayBusy,
                'blackouts' => $dayBlackouts,
                ...$this->buildOptionsForDay(
                    date: $date,
                    open: $open,
                    close: $close,
                    timezone: $timezone,
                    mode: $mode,
                    busy: $dayBusy,
                    blackouts: $dayBlackouts,
                ),
            ];
        }

        return [
            'timezone' => $timezone,
            'booking_mode' => $mode->value,
            'slot_duration_minutes' => $this->settings->getInt('slot_duration_minutes'),
            'slot_step_minutes' => $this->settings->getInt('slot_step_minutes'),
            'min_duration_minutes' => $this->settings->getInt('min_duration_minutes'),
            'max_duration_minutes' => $this->settings->getInt('max_duration_minutes'),
            'days' => $days,
        ];
    }

    /**
     * @param  Collection<int, mixed>  $intervals
     * @return array<string, array<int, array{start: string, end: string, reason?: string|null}>>
     */
    private function groupByLocalDate(Collection $intervals, string $timezone): array
    {
        $grouped = [];

        foreach ($intervals as $interval) {
            $start = CarbonImmutable::parse($interval->starts_at)->setTimezone('UTC');
            $end = CarbonImmutable::parse($interval->ends_at)->setTimezone('UTC');

            $localDate = $start->setTimezone($timezone)->format('Y-m-d');

            $grouped[$localDate] ??= [];
            $grouped[$localDate][] = [
                'start' => $start->toIso8601String(),
                'end' => $end->toIso8601String(),
                'reason' => $interval->reason ?? null,
            ];
        }

        return $grouped;
    }

    /**
     * @param  array<int, array{start: string, end: string, reason?: string|null}>  $busy
     * @param  array<int, array{start: string, end: string, reason?: string|null}>  $blackouts
     * @return array<string, mixed>
     */
    private function buildOptionsForDay(
        string $date,
        string $open,
        string $close,
        string $timezone,
        BookingMode $mode,
        array $busy,
        array $blackouts,
    ): array {
        if ($mode === BookingMode::FixedDuration) {
            return [
                'slots' => $this->buildFixedDurationSlots($date, $open, $close, $timezone, $busy, $blackouts),
            ];
        }

        if ($mode === BookingMode::PredefinedBlocks) {
            return [
                'blocks' => $this->buildPredefinedBlocks($date, $timezone, $busy, $blackouts),
            ];
        }

        return [
            'start_times' => $this->buildVariableStartTimes($date, $open, $close, $timezone, $busy, $blackouts),
        ];
    }

    /**
     * @param  array<int, array{start: string, end: string}>  $busy
     * @param  array<int, array{start: string, end: string}>  $blackouts
     * @return array<int, array{start: string, end: string, status: string}>
     */
    private function buildFixedDurationSlots(string $date, string $open, string $close, string $timezone, array $busy, array $blackouts): array
    {
        $duration = $this->settings->getInt('slot_duration_minutes');
        $step = $this->settings->getInt('slot_step_minutes');

        $openAt = CarbonImmutable::parse("{$date} {$open}", $timezone);
        $closeAt = CarbonImmutable::parse("{$date} {$close}", $timezone);

        $slots = [];
        for ($cursor = $openAt; $cursor->addMinutes($duration)->lessThanOrEqualTo($closeAt); $cursor = $cursor->addMinutes($step)) {
            $slotStartUtc = $cursor->setTimezone('UTC');
            $slotEndUtc = $cursor->addMinutes($duration)->setTimezone('UTC');

            $isOccupied = $this->overlapsAny($slotStartUtc, $slotEndUtc, $busy);
            $isBlackout = $this->overlapsAny($slotStartUtc, $slotEndUtc, $blackouts);

            $status = $isBlackout ? 'blocked' : ($isOccupied ? 'occupied' : 'free');

            $slots[] = [
                'start' => $slotStartUtc->toIso8601String(),
                'end' => $slotEndUtc->toIso8601String(),
                'status' => $status,
            ];
        }

        return $slots;
    }

    /**
     * @param  array<int, array{start: string, end: string}>  $busy
     * @param  array<int, array{start: string, end: string}>  $blackouts
     * @return array<int, array{start: string, end: string, status: string}>
     */
    private function buildPredefinedBlocks(string $date, string $timezone, array $busy, array $blackouts): array
    {
        $blocksByDay = $this->settings->get('predefined_blocks');
        $weekday = strtolower(CarbonImmutable::parse($date, $timezone)->format('D'));

        $blocks = is_array($blocksByDay) ? ($blocksByDay[$weekday] ?? []) : [];

        $options = [];
        foreach ($blocks as $block) {
            if (! is_array($block) || ! isset($block['start'], $block['end'])) {
                continue;
            }

            $startLocal = CarbonImmutable::parse("{$date} {$block['start']}", $timezone);
            $endLocal = CarbonImmutable::parse("{$date} {$block['end']}", $timezone);

            $startUtc = $startLocal->setTimezone('UTC');
            $endUtc = $endLocal->setTimezone('UTC');

            $isOccupied = $this->overlapsAny($startUtc, $endUtc, $busy);
            $isBlackout = $this->overlapsAny($startUtc, $endUtc, $blackouts);

            $status = $isBlackout ? 'blocked' : ($isOccupied ? 'occupied' : 'free');

            $options[] = [
                'start' => $startUtc->toIso8601String(),
                'end' => $endUtc->toIso8601String(),
                'status' => $status,
            ];
        }

        return $options;
    }

    /**
     * @param  array<int, array{start: string, end: string}>  $busy
     * @param  array<int, array{start: string, end: string}>  $blackouts
     * @return array<int, array{start: string, status: string}>
     */
    private function buildVariableStartTimes(string $date, string $open, string $close, string $timezone, array $busy, array $blackouts): array
    {
        $step = $this->settings->getInt('slot_step_minutes');
        $minDuration = $this->settings->getInt('min_duration_minutes');

        $openAt = CarbonImmutable::parse("{$date} {$open}", $timezone);
        $closeAt = CarbonImmutable::parse("{$date} {$close}", $timezone);

        $times = [];
        for ($cursor = $openAt; $cursor->addMinutes($minDuration)->lessThanOrEqualTo($closeAt); $cursor = $cursor->addMinutes($step)) {
            $startUtc = $cursor->setTimezone('UTC');
            $minEndUtc = $cursor->addMinutes($minDuration)->setTimezone('UTC');

            $isOccupied = $this->overlapsAny($startUtc, $minEndUtc, $busy);
            $isBlackout = $this->overlapsAny($startUtc, $minEndUtc, $blackouts);

            $status = $isBlackout ? 'blocked' : ($isOccupied ? 'occupied' : 'free');

            $times[] = [
                'start' => $startUtc->toIso8601String(),
                'status' => $status,
            ];
        }

        return $times;
    }

    /**
     * @param  array<int, array{start: string, end: string}>  $intervals
     */
    private function overlapsAny(CarbonImmutable $startUtc, CarbonImmutable $endUtc, array $intervals): bool
    {
        foreach ($intervals as $interval) {
            $busyStart = CarbonImmutable::parse($interval['start'])->setTimezone('UTC');
            $busyEnd = CarbonImmutable::parse($interval['end'])->setTimezone('UTC');

            if ($busyStart->lessThan($endUtc) && $busyEnd->greaterThan($startUtc)) {
                return true;
            }
        }

        return false;
    }
}
