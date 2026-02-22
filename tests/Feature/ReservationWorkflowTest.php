<?php

use App\Jobs\GenerateReservationPdf;
use App\Jobs\SendReservationEmail;
use App\Mail\ReservationStatusMail;
use App\Models\AllowListEntry;
use App\Models\AuditEvent;
use App\Models\Blackout;
use App\Models\Enums\ReservationArtifactKind;
use App\Models\Enums\ReservationArtifactStatus;
use App\Models\Enums\ReservationStatus;
use App\Models\ProfessionalSchool;
use App\Models\Reservation;
use App\Models\ReservationArtifact;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('registration is blocked when email is not in allow-list', function () {
    $email = 'student@example.edu';
    $school = ProfessionalSchool::factory()->create([
        'base_year_min' => 2020,
        'base_year_max' => 2024,
    ]);

    $response = $this->post('/register', [
        'first_name' => 'Juan',
        'last_name' => 'Pérez',
        'professional_school_id' => $school->id,
        'base_year' => 2022,
        'phone' => '999999999',
        'email' => $email,
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertSessionHasErrors('email');
    expect(User::query()->where('email', $email)->exists())->toBeFalse();

    expect(AuditEvent::query()->where('event_type', 'allow_list.registration_rejected')->exists())->toBeTrue();
});

test('registration succeeds when email is in allow-list', function () {
    $email = 'student.allowed@example.edu';

    AllowListEntry::factory()->create(['email' => $email]);
    $school = ProfessionalSchool::factory()->create([
        'base_year_min' => 2020,
        'base_year_max' => 2024,
    ]);

    $response = $this->post('/register', [
        'first_name' => 'Ana',
        'last_name' => 'García',
        'professional_school_id' => $school->id,
        'base_year' => 2022,
        'phone' => '999999999',
        'email' => $email,
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticated();

    expect(User::query()->where('email', $email)->exists())->toBeTrue();
});

test('students can create a pending reservation and it blocks availability', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $startsAtLocal = CarbonImmutable::now('America/Lima')->addDay()->setTime(10, 0);
    $startsAtUtc = $startsAtLocal->setTimezone('UTC');
    $endsAtUtc = $startsAtUtc->addHour();

    $response = $this->post(route('reservations.store'), [
        'starts_at' => $startsAtUtc->toIso8601String(),
        'ends_at' => $endsAtUtc->toIso8601String(),
    ]);

    $response->assertRedirect(route('reservations.index'));
    expect(Reservation::query()->count())->toBe(1);

    $reservation = Reservation::query()->firstOrFail();
    expect($reservation->status)->toBe(ReservationStatus::Pending);

    $conflict = Reservation::query()
        ->blocking()
        ->overlapping($reservation->starts_at, $reservation->ends_at)
        ->exists();

    expect($conflict)->toBeTrue();
});

test('creating a reservation on an occupied slot is rejected', function () {
    $startsAtLocal = CarbonImmutable::now('America/Lima')->addDay()->setTime(11, 0);
    $startsAtUtc = $startsAtLocal->setTimezone('UTC');
    $endsAtUtc = $startsAtUtc->addHour();

    $user1 = User::factory()->create();
    $this->actingAs($user1);

    $this->post(route('reservations.store'), [
        'starts_at' => $startsAtUtc->toIso8601String(),
        'ends_at' => $endsAtUtc->toIso8601String(),
    ])->assertRedirect();

    $user2 = User::factory()->create();
    $this->actingAs($user2);

    $this->post(route('reservations.store'), [
        'starts_at' => $startsAtUtc->toIso8601String(),
        'ends_at' => $endsAtUtc->toIso8601String(),
    ])->assertSessionHasErrors('starts_at');
});

test('students are limited by max active reservations per user', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $start1 = CarbonImmutable::now('America/Lima')->addDay()->setTime(12, 0)->setTimezone('UTC');
    $start2 = CarbonImmutable::now('America/Lima')->addDays(2)->setTime(12, 0)->setTimezone('UTC');

    $this->post(route('reservations.store'), [
        'starts_at' => $start1->toIso8601String(),
        'ends_at' => $start1->addHour()->toIso8601String(),
    ])->assertRedirect();

    $this->post(route('reservations.store'), [
        'starts_at' => $start2->toIso8601String(),
        'ends_at' => $start2->addHour()->toIso8601String(),
    ])
        ->assertSessionHasErrors('reservation');
});

test('weekly quota is enforced per school and base', function () {
    $school = ProfessionalSchool::factory()->create([
        'base_year_min' => 2020,
        'base_year_max' => 2024,
    ]);

    $baseUser = User::factory()->create([
        'professional_school_id' => $school->id,
        'base_year' => 2022,
    ]);

    $startA = CarbonImmutable::now('America/Lima')->addDay()->setTime(10, 0)->setTimezone('UTC');
    $startB = CarbonImmutable::now('America/Lima')->addDay()->setTime(12, 0)->setTimezone('UTC');
    $startC = CarbonImmutable::now('America/Lima')->addDay()->setTime(14, 0)->setTimezone('UTC');

    $user1 = User::factory()->create([
        'professional_school_id' => $baseUser->professional_school_id,
        'base_year' => $baseUser->base_year,
    ]);
    $user2 = User::factory()->create([
        'professional_school_id' => $baseUser->professional_school_id,
        'base_year' => $baseUser->base_year,
    ]);
    $user3 = User::factory()->create([
        'professional_school_id' => $baseUser->professional_school_id,
        'base_year' => $baseUser->base_year,
    ]);

    $this->actingAs($user1);
    $this->post(route('reservations.store'), [
        'starts_at' => $startA->toIso8601String(),
        'ends_at' => $startA->addHour()->toIso8601String(),
    ])->assertRedirect();

    $this->actingAs($user2);
    $this->post(route('reservations.store'), [
        'starts_at' => $startB->toIso8601String(),
        'ends_at' => $startB->addHour()->toIso8601String(),
    ])->assertRedirect();

    $this->actingAs($user3);
    $this->post(route('reservations.store'), [
        'starts_at' => $startC->toIso8601String(),
        'ends_at' => $startC->addHour()->toIso8601String(),
    ])
        ->assertSessionHasErrors('reservation');
});

test('admins cannot approve a reservation if a conflict exists at approval time', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $startsAtUtc = CarbonImmutable::now('America/Lima')->addDay()->setTime(14, 0)->setTimezone('UTC');

    $pending = Reservation::factory()->create([
        'user_id' => $user->id,
        'status' => ReservationStatus::Pending,
        'starts_at' => $startsAtUtc,
        'ends_at' => $startsAtUtc->addHour(),
        'professional_school_id' => $user->professional_school_id,
        'base_year' => $user->base_year,
    ]);

    Reservation::factory()->create([
        'user_id' => User::factory(),
        'status' => ReservationStatus::Approved,
        'starts_at' => $startsAtUtc,
        'ends_at' => $startsAtUtc->addHour(),
        'professional_school_id' => $user->professional_school_id,
        'base_year' => $user->base_year,
    ]);

    $this->actingAs($admin);
    $this->post(route('admin.requests.approve', $pending), ['reason' => null])
        ->assertSessionHasErrors('starts_at');
});

test('approving a reservation enqueues pdf and email artifacts', function () {
    Queue::fake();

    Setting::query()->create([
        'key' => 'email_notifications_enabled',
        'value' => true,
        'updated_by' => null,
    ]);

    Setting::query()->create([
        'key' => 'notify_admin_emails',
        'value' => ['to' => ['admin.notify@example.edu'], 'cc' => [], 'bcc' => []],
        'updated_by' => null,
    ]);

    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $startsAtUtc = CarbonImmutable::now('America/Lima')->addDay()->setTime(15, 0)->setTimezone('UTC');
    $reservation = Reservation::factory()->create([
        'user_id' => $user->id,
        'status' => ReservationStatus::Pending,
        'starts_at' => $startsAtUtc,
        'ends_at' => $startsAtUtc->addHour(),
        'professional_school_id' => $user->professional_school_id,
        'base_year' => $user->base_year,
    ]);

    $this->actingAs($admin);
    $this->post(route('admin.requests.approve', $reservation), ['reason' => null])->assertRedirect();

    expect(ReservationArtifact::query()->where('reservation_id', $reservation->id)->where('kind', ReservationArtifactKind::Pdf)->exists())->toBeTrue();
    expect(ReservationArtifact::query()->where('reservation_id', $reservation->id)->where('kind', ReservationArtifactKind::EmailAdmin)->exists())->toBeTrue();
    expect(ReservationArtifact::query()->where('reservation_id', $reservation->id)->where('kind', ReservationArtifactKind::EmailStudent)->exists())->toBeTrue();

    Queue::assertPushed(GenerateReservationPdf::class);
    Queue::assertPushed(SendReservationEmail::class);
});

test('pending reservations can expire via artisan command', function () {
    $reservation = Reservation::factory()->create([
        'status' => ReservationStatus::Pending,
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ]);

    $this->artisan('reservations:expire-pending')->assertExitCode(0);

    $reservation->refresh();
    expect($reservation->status)->toBe(ReservationStatus::Rejected);
    expect($reservation->decision_reason)->toBe('Expirada por falta de aprobación.');
});

test('students can cancel a pending reservation', function () {
    Queue::fake();

    $user = User::factory()->create();
    $this->actingAs($user);

    $startsAtUtc = CarbonImmutable::now('America/Lima')->addHours(6)->setTimezone('UTC');

    $reservation = Reservation::factory()->create([
        'user_id' => $user->id,
        'status' => ReservationStatus::Pending,
        'starts_at' => $startsAtUtc,
        'ends_at' => $startsAtUtc->addHour(),
        'professional_school_id' => $user->professional_school_id,
        'base_year' => $user->base_year,
    ]);

    $this->post(route('reservations.cancel', $reservation), ['reason' => 'Cambio de planes'])
        ->assertRedirect(route('reservations.index'));

    $reservation->refresh();
    expect($reservation->status)->toBe(ReservationStatus::Cancelled);
    expect($reservation->cancelled_by)->toBe($user->id);
    expect($reservation->cancellation_reason)->toBe('Cambio de planes');

    expect(AuditEvent::query()->where('event_type', 'reservation.cancelled')->where('subject_id', $reservation->id)->exists())->toBeTrue();

    Queue::assertNotPushed(SendReservationEmail::class);
});

test('cancellation cutoff is enforced', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $startsAtUtc = CarbonImmutable::now('America/Lima')->addHour()->setTimezone('UTC');

    $reservation = Reservation::factory()->create([
        'user_id' => $user->id,
        'status' => ReservationStatus::Pending,
        'starts_at' => $startsAtUtc,
        'ends_at' => $startsAtUtc->addHour(),
        'professional_school_id' => $user->professional_school_id,
        'base_year' => $user->base_year,
    ]);

    $this->post(route('reservations.cancel', $reservation), ['reason' => null])
        ->assertSessionHasErrors('reservation');

    $reservation->refresh();
    expect($reservation->status)->toBe(ReservationStatus::Pending);
});

test('approved reservations can be cancelled by the student', function () {
    Queue::fake();

    $user = User::factory()->create();
    $this->actingAs($user);

    $startsAtUtc = CarbonImmutable::now('America/Lima')->addHours(6)->setTimezone('UTC');

    $reservation = Reservation::factory()->create([
        'user_id' => $user->id,
        'status' => ReservationStatus::Approved,
        'starts_at' => $startsAtUtc,
        'ends_at' => $startsAtUtc->addHour(),
        'professional_school_id' => $user->professional_school_id,
        'base_year' => $user->base_year,
    ]);

    $this->post(route('reservations.cancel', $reservation), ['reason' => null])
        ->assertRedirect(route('reservations.index'));

    $reservation->refresh();
    expect($reservation->status)->toBe(ReservationStatus::Cancelled);

    expect(AuditEvent::query()->where('event_type', 'reservation.cancelled')->where('subject_id', $reservation->id)->exists())->toBeTrue();

    Queue::assertNotPushed(SendReservationEmail::class);
});

test('students cannot cancel another student reservation', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();

    $startsAtUtc = CarbonImmutable::now('America/Lima')->addHours(6)->setTimezone('UTC');

    $reservation = Reservation::factory()->create([
        'user_id' => $owner->id,
        'status' => ReservationStatus::Pending,
        'starts_at' => $startsAtUtc,
        'ends_at' => $startsAtUtc->addHour(),
        'professional_school_id' => $owner->professional_school_id,
        'base_year' => $owner->base_year,
    ]);

    $this->actingAs($attacker)
        ->post(route('reservations.cancel', $reservation), ['reason' => null])
        ->assertForbidden();
});

test('admins can reject a pending reservation end-to-end', function () {
    Queue::fake();

    Setting::query()->create([
        'key' => 'email_notifications_enabled',
        'value' => true,
        'updated_by' => null,
    ]);

    Setting::query()->create([
        'key' => 'notify_admin_emails',
        'value' => ['to' => ['admin.notify@example.edu'], 'cc' => [], 'bcc' => []],
        'updated_by' => null,
    ]);

    $admin = User::factory()->admin()->create();
    $student = User::factory()->create();

    $startsAtUtc = CarbonImmutable::now('America/Lima')->addDay()->setTime(16, 0)->setTimezone('UTC');

    $reservation = Reservation::factory()->create([
        'user_id' => $student->id,
        'status' => ReservationStatus::Pending,
        'starts_at' => $startsAtUtc,
        'ends_at' => $startsAtUtc->addHour(),
        'professional_school_id' => $student->professional_school_id,
        'base_year' => $student->base_year,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.requests.reject', $reservation), ['reason' => 'No disponible'])
        ->assertRedirect(route('admin.requests.index'));

    $reservation->refresh();
    expect($reservation->status)->toBe(ReservationStatus::Rejected);
    expect($reservation->decided_by)->toBe($admin->id);
    expect($reservation->decision_reason)->toBe('No disponible');

    expect(AuditEvent::query()->where('event_type', 'reservation.rejected')->where('subject_id', $reservation->id)->exists())->toBeTrue();
    expect(ReservationArtifact::query()->where('reservation_id', $reservation->id)->where('kind', ReservationArtifactKind::EmailAdmin)->exists())->toBeTrue();
    expect(ReservationArtifact::query()->where('reservation_id', $reservation->id)->where('kind', ReservationArtifactKind::EmailStudent)->exists())->toBeTrue();

    Queue::assertPushedTimes(SendReservationEmail::class, 2);
});

test('public availability marks approved reservations as occupied', function () {
    $timezone = 'America/Lima';
    $date = CarbonImmutable::now($timezone)->addDay()->format('Y-m-d');
    $endDate = CarbonImmutable::parse($date, $timezone)->addDay()->format('Y-m-d');

    $startUtc = CarbonImmutable::parse("{$date} 10:00", $timezone)->setTimezone('UTC');

    Reservation::factory()->create([
        'status' => ReservationStatus::Approved,
        'starts_at' => $startUtc,
        'ends_at' => $startUtc->addHour(),
    ]);

    $response = $this->getJson(route('api.public.availability', [
        'start' => $date,
        'end' => $endDate,
    ]));

    $response->assertOk();

    $events = $response->json();
    expect($events)->toBeArray();

    $reservationEventStart = $startUtc->setTimezone($timezone)->toIso8601String();

    $reservationEvent = collect($events)->firstWhere('start', $reservationEventStart);
    expect($reservationEvent)->not->toBeNull();
    expect($reservationEvent['title'] ?? null)->toBe('Ocupado');
    expect($reservationEvent['color'] ?? null)->toBe('#f59e0b');
    expect($reservationEvent['extendedProps']['type'] ?? null)->toBe('reservation');
});

test('public availability returns calendar events for a date range', function () {
    $timezone = 'America/Lima';
    $date = CarbonImmutable::now($timezone)->addDay()->format('Y-m-d');
    $endDate = CarbonImmutable::parse($date, $timezone)->addDay()->format('Y-m-d');

    $startUtc = CarbonImmutable::parse("{$date} 10:00", $timezone)->setTimezone('UTC');

    Reservation::factory()->create([
        'status' => ReservationStatus::Approved,
        'starts_at' => $startUtc,
        'ends_at' => $startUtc->addHour(),
    ]);

    Blackout::factory()->create([
        'starts_at' => $startUtc->addHours(3),
        'ends_at' => $startUtc->addHours(4),
        'reason' => 'Mantenimiento',
    ]);

    $response = $this->getJson(route('api.public.availability', [
        'start' => $date,
        'end' => $endDate,
    ]));

    $response->assertOk();

    $events = $response->json();
    expect($events)->toBeArray();

    $reservationEventStart = $startUtc->setTimezone($timezone)->toIso8601String();

    $reservationEvent = collect($events)->firstWhere('start', $reservationEventStart);
    expect($reservationEvent)->not->toBeNull();
    expect($reservationEvent['extendedProps']['type'] ?? null)->toBe('reservation');

    $blackoutEvent = collect($events)->firstWhere('extendedProps.type', 'blackout');
    expect($blackoutEvent)->not->toBeNull();
    expect($blackoutEvent['display'] ?? null)->toBe('background');
});

test('pdf generation job stores the pdf and marks the artifact as sent', function () {
    Storage::fake('local');

    $reservation = Reservation::factory()->create();

    $artifact = ReservationArtifact::factory()->create([
        'reservation_id' => $reservation->id,
        'kind' => ReservationArtifactKind::Pdf,
        'status' => ReservationArtifactStatus::Pending,
        'payload' => ['template' => 'default'],
    ]);

    (new GenerateReservationPdf($artifact->id))->handle(app(\App\Actions\Settings\SettingsService::class));

    $artifact->refresh();

    expect($artifact->status)->toBe(ReservationArtifactStatus::Sent);
    expect($artifact->payload['path'] ?? null)->toBeString();

    Storage::disk('local')->assertExists((string) $artifact->payload['path']);
});

test('email sending job sends the mailable and marks the artifact as sent', function () {
    Mail::fake();
    Storage::fake('local');

    $reservation = Reservation::factory()->create();

    $pdfPath = "reservations/{$reservation->id}/reservation.pdf";
    Storage::disk('local')->put($pdfPath, 'pdf-bytes');

    ReservationArtifact::factory()->create([
        'reservation_id' => $reservation->id,
        'kind' => ReservationArtifactKind::Pdf,
        'status' => ReservationArtifactStatus::Sent,
        'payload' => ['path' => $pdfPath, 'template' => 'default'],
    ]);

    $artifact = ReservationArtifact::factory()->create([
        'reservation_id' => $reservation->id,
        'kind' => ReservationArtifactKind::EmailStudent,
        'status' => ReservationArtifactStatus::Pending,
        'payload' => [
            'event' => 'approved',
            'to' => ['student@example.edu'],
            'cc' => [],
            'bcc' => [],
        ],
    ]);

    (new SendReservationEmail($artifact->id))->handle(app(\App\Actions\Settings\SettingsService::class));

    $artifact->refresh();
    expect($artifact->status)->toBe(ReservationArtifactStatus::Sent);

    Mail::assertSent(ReservationStatusMail::class, function (ReservationStatusMail $mail) use ($reservation): bool {
        return $mail->reservation->id === $reservation->id
            && $mail->event === 'approved'
            && is_string($mail->attachmentPath)
            && $mail->attachmentPath !== '';
    });
});
