<?php

use App\Models\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;

test('student can download pdf for their approved reservation', function () {
    $student = User::factory()->create();
    $this->actingAs($student);

    $startsAtUtc = CarbonImmutable::now('America/Lima')->addDay()->setTime(10, 0)->setTimezone('UTC');
    $reservation = Reservation::factory()->create([
        'user_id' => $student->id,
        'status' => ReservationStatus::Approved,
        'starts_at' => $startsAtUtc,
        'ends_at' => $startsAtUtc->addHour(),
        'professional_school_id' => $student->professional_school_id,
        'base_year' => $student->base_year,
    ]);

    $pdfDocument = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
    $pdfDocument->shouldReceive('download')
        ->once()
        ->with("reserva-{$reservation->id}.pdf")
        ->andReturn(response('pdf-content', 200, ['Content-Type' => 'application/pdf']));
    Pdf::shouldReceive('loadView')->once()->andReturn($pdfDocument);

    $this->get(route('reservations.pdf.show', $reservation))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('student cannot download another student reservation pdf', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();

    $startsAtUtc = CarbonImmutable::now('America/Lima')->addDay()->setTime(10, 0)->setTimezone('UTC');
    $reservation = Reservation::factory()->create([
        'user_id' => $owner->id,
        'status' => ReservationStatus::Approved,
        'starts_at' => $startsAtUtc,
        'ends_at' => $startsAtUtc->addHour(),
        'professional_school_id' => $owner->professional_school_id,
        'base_year' => $owner->base_year,
    ]);

    $this->actingAs($attacker)
        ->get(route('reservations.pdf.show', $reservation))
        ->assertForbidden();
});

test('operator can download another student reservation pdf', function () {
    $owner = User::factory()->create();
    $operator = User::factory()->operator()->create();

    $startsAtUtc = CarbonImmutable::now('America/Lima')->addDay()->setTime(10, 0)->setTimezone('UTC');
    $reservation = Reservation::factory()->create([
        'user_id' => $owner->id,
        'status' => ReservationStatus::Approved,
        'starts_at' => $startsAtUtc,
        'ends_at' => $startsAtUtc->addHour(),
        'professional_school_id' => $owner->professional_school_id,
        'base_year' => $owner->base_year,
    ]);

    $pdfDocument = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
    $pdfDocument->shouldReceive('download')
        ->once()
        ->andReturn(response('pdf-content', 200, ['Content-Type' => 'application/pdf']));
    Pdf::shouldReceive('loadView')->once()->andReturn($pdfDocument);

    $this->actingAs($operator)
        ->get(route('reservations.pdf.show', $reservation))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('pdf download returns 404 when reservation is not approved', function () {
    $student = User::factory()->create();
    $this->actingAs($student);

    $startsAtUtc = CarbonImmutable::now('America/Lima')->addDay()->setTime(10, 0)->setTimezone('UTC');
    $reservation = Reservation::factory()->create([
        'user_id' => $student->id,
        'status' => ReservationStatus::Pending,
        'starts_at' => $startsAtUtc,
        'ends_at' => $startsAtUtc->addHour(),
        'professional_school_id' => $student->professional_school_id,
        'base_year' => $student->base_year,
    ]);

    $this->get(route('reservations.pdf.show', $reservation))
        ->assertNotFound();
});
