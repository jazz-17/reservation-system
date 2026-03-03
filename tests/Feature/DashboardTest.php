<?php

use App\Models\Blackout;
use App\Models\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;
use Carbon\CarbonImmutable;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard returns correct props for student', function () {
    $student = User::factory()->create();
    $this->actingAs($student);

    $response = $this->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->has('upcoming_reservations')
        ->has('active_count')
        ->has('max_active')
        ->has('weekly_quota_used')
        ->has('weekly_quota_max')
        ->has('recent_activity')
        ->has('upcoming_blackouts')
        ->has('todays_opening_hours')
    );
});

test('dashboard shows correct active count', function () {
    $student = User::factory()->create();
    $this->actingAs($student);

    $startsAtUtc = CarbonImmutable::now('America/Lima')->addDay()->setTime(10, 0)->setTimezone('UTC');

    // pending reservation (counts as blocking)
    Reservation::factory()->create([
        'user_id' => $student->id,
        'status' => ReservationStatus::Pending,
        'starts_at' => $startsAtUtc,
        'ends_at' => $startsAtUtc->addHour(),
        'professional_school_id' => $student->professional_school_id,
        'base_year' => $student->base_year,
    ]);

    // approved reservation (counts as blocking)
    Reservation::factory()->create([
        'user_id' => $student->id,
        'status' => ReservationStatus::Approved,
        'starts_at' => $startsAtUtc->addHours(3),
        'ends_at' => $startsAtUtc->addHours(4),
        'professional_school_id' => $student->professional_school_id,
        'base_year' => $student->base_year,
    ]);

    // cancelled reservation (should NOT count)
    Reservation::factory()->create([
        'user_id' => $student->id,
        'status' => ReservationStatus::Cancelled,
        'starts_at' => $startsAtUtc->addHours(5),
        'ends_at' => $startsAtUtc->addHours(6),
        'professional_school_id' => $student->professional_school_id,
        'base_year' => $student->base_year,
    ]);

    $response = $this->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('active_count', 2)
    );
});

test('dashboard shows upcoming blackouts', function () {
    $student = User::factory()->create();
    $this->actingAs($student);

    $tomorrow = CarbonImmutable::now('UTC')->addDay();

    Blackout::create([
        'starts_at' => $tomorrow,
        'ends_at' => $tomorrow->addHours(3),
        'reason' => 'Mantenimiento',
        'created_by' => $student->id,
    ]);

    $response = $this->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->has('upcoming_blackouts', 1)
    );
});

test('dashboard does not show past blackouts', function () {
    $student = User::factory()->create();
    $this->actingAs($student);

    $yesterday = CarbonImmutable::now('UTC')->subDay();

    Blackout::create([
        'starts_at' => $yesterday->subHours(3),
        'ends_at' => $yesterday,
        'reason' => 'Ya pasó',
        'created_by' => $student->id,
    ]);

    $response = $this->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->has('upcoming_blackouts', 0)
    );
});

test('dashboard shows upcoming reservations sorted by start time', function () {
    $student = User::factory()->create();
    $this->actingAs($student);

    $baseTime = CarbonImmutable::now('America/Lima')->addDay()->setTime(10, 0)->setTimezone('UTC');

    // Create in reverse order to verify sorting
    $later = Reservation::factory()->create([
        'user_id' => $student->id,
        'status' => ReservationStatus::Approved,
        'starts_at' => $baseTime->addHours(5),
        'ends_at' => $baseTime->addHours(6),
        'professional_school_id' => $student->professional_school_id,
        'base_year' => $student->base_year,
    ]);

    $sooner = Reservation::factory()->create([
        'user_id' => $student->id,
        'status' => ReservationStatus::Pending,
        'starts_at' => $baseTime,
        'ends_at' => $baseTime->addHour(),
        'professional_school_id' => $student->professional_school_id,
        'base_year' => $student->base_year,
    ]);

    $response = $this->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->has('upcoming_reservations', 2)
        ->where('upcoming_reservations.0.id', $sooner->id)
        ->where('upcoming_reservations.1.id', $later->id)
    );
});
