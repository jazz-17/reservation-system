<?php

use App\Actions\Reservations\ReservationRulesService;
use App\Models\Blackout;
use App\Models\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

test('minute precision prevents false conflicts from seconds in stored reservations', function () {
    $timezone = 'America/Lima';

    $existingStartsAtUtc = CarbonImmutable::now($timezone)->addDay()->setTime(10, 0)->setTimezone('UTC');
    $existingEndsAtUtc = $existingStartsAtUtc->addHour()->addSeconds(30);

    Reservation::factory()->create([
        'status' => ReservationStatus::Approved,
        'starts_at' => $existingStartsAtUtc,
        'ends_at' => $existingEndsAtUtc,
    ]);

    $user = User::factory()->create();

    $requestedStartsAtUtc = CarbonImmutable::now($timezone)->addDay()->setTime(11, 0)->setTimezone('UTC');
    $requestedEndsAtUtc = $requestedStartsAtUtc->addHour();

    expect(fn () => app(ReservationRulesService::class)->validateForCreation($user, $requestedStartsAtUtc, $requestedEndsAtUtc))
        ->not->toThrow(ValidationException::class);
});

test('opening hours allow ending exactly at close time', function () {
    $timezone = 'America/Lima';

    $user = User::factory()->create();

    $startsAtUtc = CarbonImmutable::now($timezone)->addDay()->setTime(21, 0)->setTimezone('UTC');
    $endsAtUtc = $startsAtUtc->addHour();

    expect(fn () => app(ReservationRulesService::class)->validateForCreation($user, $startsAtUtc, $endsAtUtc))
        ->not->toThrow(ValidationException::class);
});

test('blackouts block overlapping reservations', function () {
    $timezone = 'America/Lima';

    $user = User::factory()->create();

    $startsAtUtc = CarbonImmutable::now($timezone)->addDay()->setTime(11, 0)->setTimezone('UTC');
    $endsAtUtc = $startsAtUtc->addHour();

    Blackout::factory()->create([
        'starts_at' => $startsAtUtc->subMinutes(30),
        'ends_at' => $startsAtUtc->addMinutes(30),
    ]);

    expect(fn () => app(ReservationRulesService::class)->validateForCreation($user, $startsAtUtc, $endsAtUtc))
        ->toThrow(ValidationException::class);
});
