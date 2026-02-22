<?php

namespace App\Actions\Reservations;

use App\Actions\Settings\SettingsService;
use App\Models\Blackout;
use App\Models\Reservation;
use Carbon\CarbonImmutable;

class AvailabilityService
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function eventsForRange(string $start, string $end): array
    {
        $timezone = $this->settings->getString('timezone');

        $startUtc = CarbonImmutable::parse($start, $timezone)->setTimezone('UTC');
        $endUtc = CarbonImmutable::parse($end, $timezone)->setTimezone('UTC');

        $reservations = Reservation::query()
            ->blocking()
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

        usort($events, function (array $a, array $b): int {
            return strcmp((string) $a['start'], (string) $b['start']);
        });

        return $events;
    }
}
