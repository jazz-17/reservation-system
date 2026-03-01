<?php

use App\Models\Enums\ReservationArtifactKind;
use App\Models\Enums\ReservationArtifactStatus;
use App\Models\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\ReservationArtifact;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

test('student can download pdf for their approved reservation (stored artifact)', function () {
    Storage::fake('local');

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

    $path = "reservations/{$reservation->id}/reservation.pdf";
    Storage::disk('local')->put($path, '%PDF-1.4 stored');

    ReservationArtifact::factory()->create([
        'reservation_id' => $reservation->id,
        'kind' => ReservationArtifactKind::Pdf,
        'status' => ReservationArtifactStatus::Sent,
        'payload' => ['path' => $path, 'template' => 'default'],
    ]);

    Pdf::shouldReceive('loadView')->never();

    $this->get(route('reservations.pdf.show', $reservation))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('student cannot download another student reservation pdf', function () {
    Storage::fake('local');

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

test('auditor can download another student reservation pdf', function () {
    Storage::fake('local');

    $owner = User::factory()->create();
    $auditor = User::factory()->auditor()->create();

    $startsAtUtc = CarbonImmutable::now('America/Lima')->addDay()->setTime(10, 0)->setTimezone('UTC');
    $reservation = Reservation::factory()->create([
        'user_id' => $owner->id,
        'status' => ReservationStatus::Approved,
        'starts_at' => $startsAtUtc,
        'ends_at' => $startsAtUtc->addHour(),
        'professional_school_id' => $owner->professional_school_id,
        'base_year' => $owner->base_year,
    ]);

    $path = "reservations/{$reservation->id}/reservation.pdf";
    Storage::disk('local')->put($path, '%PDF-1.4 stored');

    ReservationArtifact::factory()->create([
        'reservation_id' => $reservation->id,
        'kind' => ReservationArtifactKind::Pdf,
        'status' => ReservationArtifactStatus::Sent,
        'payload' => ['path' => $path, 'template' => 'default'],
    ]);

    Pdf::shouldReceive('loadView')->never();

    $this->actingAs($auditor)
        ->get(route('reservations.pdf.show', $reservation))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('pdf download returns 404 when reservation is not approved', function () {
    Storage::fake('local');

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

test('pdf download generates and stores pdf when artifact is missing', function () {
    Storage::fake('local');

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
    $pdfDocument->shouldReceive('output')->once()->andReturn('%PDF-1.4 generated');
    Pdf::shouldReceive('loadView')->once()->andReturn($pdfDocument);

    $this->get(route('reservations.pdf.show', $reservation))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $path = "reservations/{$reservation->id}/reservation.pdf";
    Storage::disk('local')->assertExists($path);

    $artifact = ReservationArtifact::query()
        ->where('reservation_id', $reservation->id)
        ->where('kind', ReservationArtifactKind::Pdf)
        ->first();

    expect($artifact)->not->toBeNull();
    expect($artifact?->status)->toBe(ReservationArtifactStatus::Sent);
    expect($artifact?->payload['path'] ?? null)->toBe($path);
});
