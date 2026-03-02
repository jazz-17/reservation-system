<?php

use App\Models\RecurringBlackout;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

test('public availability includes recurring blackouts', function () {
    $timezone = 'America/Lima';

    $day = CarbonImmutable::now($timezone)
        ->addWeek()
        ->startOfWeek(CarbonInterface::MONDAY)
        ->setTime(0, 0);

    RecurringBlackout::factory()->create([
        'weekday' => $day->dayOfWeek,
        'starts_time' => '14:00',
        'ends_time' => '16:00',
        'starts_on' => null,
        'ends_on' => null,
        'reason' => 'Mantenimiento semanal',
    ]);

    $response = $this->getJson(route('api.public.availability', [
        'start' => $day->toIso8601String(),
        'end' => $day->addDay()->toIso8601String(),
    ]));

    $response->assertOk();

    $events = $response->json();

    expect($events)->toBeArray();
    expect(collect($events)->contains(function (array $event): bool {
        return ($event['extendedProps']['type'] ?? null) === 'blackout'
            && ($event['display'] ?? null) === 'background'
            && is_string($event['title'] ?? null)
            && str_contains((string) $event['title'], 'Bloqueado');
    }))->toBeTrue();
});
