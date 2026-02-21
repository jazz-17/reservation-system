<?php

use App\Jobs\GenerateReservationPdf;
use App\Jobs\SendReservationEmail;
use App\Models\AllowListEntry;
use App\Models\AuditEvent;
use App\Models\Enums\ReservationArtifactKind;
use App\Models\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\ReservationArtifact;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;

test('registration is blocked when email is not in allow-list', function () {
    $email = 'student@example.edu';

    $response = $this->post('/register', [
        'first_name' => 'Juan',
        'last_name' => 'Pérez',
        'professional_school' => 'E.P. Sistemas',
        'base' => 'B22',
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

    $response = $this->post('/register', [
        'first_name' => 'Ana',
        'last_name' => 'García',
        'professional_school' => 'E.P. Sistemas',
        'base' => 'B22',
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

    $response = $this->post(route('reservations.store'), [
        'starts_at' => $startsAtUtc->toIso8601String(),
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

    $user1 = User::factory()->create();
    $this->actingAs($user1);

    $this->post(route('reservations.store'), [
        'starts_at' => $startsAtUtc->toIso8601String(),
    ])->assertRedirect();

    $user2 = User::factory()->create();
    $this->actingAs($user2);

    $this->post(route('reservations.store'), [
        'starts_at' => $startsAtUtc->toIso8601String(),
    ])->assertSessionHasErrors('starts_at');
});

test('students are limited by max active reservations per user', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $start1 = CarbonImmutable::now('America/Lima')->addDay()->setTime(12, 0)->setTimezone('UTC');
    $start2 = CarbonImmutable::now('America/Lima')->addDays(2)->setTime(12, 0)->setTimezone('UTC');

    $this->post(route('reservations.store'), ['starts_at' => $start1->toIso8601String()])->assertRedirect();

    $this->post(route('reservations.store'), ['starts_at' => $start2->toIso8601String()])
        ->assertSessionHasErrors('reservation');
});

test('weekly quota is enforced per school and base', function () {
    $baseUser = User::factory()->create([
        'professional_school' => 'E.P. Sistemas',
        'base' => 'B22',
    ]);

    $startA = CarbonImmutable::now('America/Lima')->addDay()->setTime(10, 0)->setTimezone('UTC');
    $startB = CarbonImmutable::now('America/Lima')->addDay()->setTime(12, 0)->setTimezone('UTC');
    $startC = CarbonImmutable::now('America/Lima')->addDay()->setTime(14, 0)->setTimezone('UTC');

    $user1 = User::factory()->create([
        'professional_school' => $baseUser->professional_school,
        'base' => $baseUser->base,
    ]);
    $user2 = User::factory()->create([
        'professional_school' => $baseUser->professional_school,
        'base' => $baseUser->base,
    ]);
    $user3 = User::factory()->create([
        'professional_school' => $baseUser->professional_school,
        'base' => $baseUser->base,
    ]);

    $this->actingAs($user1);
    $this->post(route('reservations.store'), ['starts_at' => $startA->toIso8601String()])->assertRedirect();

    $this->actingAs($user2);
    $this->post(route('reservations.store'), ['starts_at' => $startB->toIso8601String()])->assertRedirect();

    $this->actingAs($user3);
    $this->post(route('reservations.store'), ['starts_at' => $startC->toIso8601String()])
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
        'professional_school' => $user->professional_school,
        'base' => $user->base,
    ]);

    Reservation::factory()->create([
        'user_id' => User::factory(),
        'status' => ReservationStatus::Approved,
        'starts_at' => $startsAtUtc,
        'ends_at' => $startsAtUtc->addHour(),
        'professional_school' => $user->professional_school,
        'base' => $user->base,
    ]);

    $this->actingAs($admin);
    $this->post(route('admin.requests.approve', $pending), ['reason' => null])
        ->assertSessionHasErrors('starts_at');
});

test('approving a reservation enqueues pdf and email artifacts', function () {
    Queue::fake();

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
        'professional_school' => $user->professional_school,
        'base' => $user->base,
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
