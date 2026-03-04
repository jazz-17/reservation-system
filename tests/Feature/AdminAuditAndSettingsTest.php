<?php

use App\Jobs\SendReservationEmail;
use App\Models\AuditEvent;
use App\Models\Enums\ReservationArtifactKind;
use App\Models\Reservation;
use App\Models\ReservationArtifact;
use App\Models\Setting;
use App\Models\User;
use App\Settings\SettingsSchema;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

test('admin settings updates are audited with changed keys', function () {
    $admin = User::factory()->admin()->create();

    $payload = SettingsSchema::defaults();
    $payload['min_duration_minutes'] = $payload['min_duration_minutes'] + 1;

    $response = $this->actingAs($admin)
        ->putJson(route('admin.settings.update'), $payload)
        ->assertRedirect();

    $response->assertSessionHasNoErrors();

    $stored = Setting::query()->find('min_duration_minutes');
    expect($stored)->not->toBeNull();
    expect($stored?->value)->toBe($payload['min_duration_minutes']);

    $event = AuditEvent::query()
        ->where('event_type', 'settings.updated')
        ->latest('created_at')
        ->first();

    expect($event)->not->toBeNull();
    expect($event?->actor_id)->toBe($admin->id);
    expect($event?->metadata['changed_keys'] ?? [])->toContain('min_duration_minutes');
});

test('admin settings can update notify_email_events and it is audited', function () {
    $admin = User::factory()->admin()->create();

    $payload = SettingsSchema::defaults();
    $payload['notify_email_events']['admin']['approved'] = true;

    $response = $this->actingAs($admin)
        ->putJson(route('admin.settings.update'), $payload)
        ->assertRedirect();

    $response->assertSessionHasNoErrors();

    $stored = Setting::query()->find('notify_email_events');
    expect($stored)->not->toBeNull();
    expect($stored?->value['admin']['approved'] ?? null)->toBeTrue();

    $event = AuditEvent::query()
        ->where('event_type', 'settings.updated')
        ->latest('created_at')
        ->first();

    expect($event)->not->toBeNull();
    expect($event?->metadata['changed_keys'] ?? [])->toContain('notify_email_events');
});

test('admin settings update validation rejects invalid values', function () {
    $admin = User::factory()->admin()->create();

    $payload = SettingsSchema::defaults();
    $payload['lead_time_max_days'] = 0;

    $this->actingAs($admin)
        ->putJson(route('admin.settings.update'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lead_time_max_days']);

    expect(AuditEvent::query()->where('event_type', 'settings.updated')->exists())->toBeFalse();
});

test('admin settings update validation rejects invalid notify_email_events values', function () {
    $admin = User::factory()->admin()->create();

    $payload = SettingsSchema::defaults();
    $payload['notify_email_events']['admin']['pending'] = 'nope';

    $this->actingAs($admin)
        ->putJson(route('admin.settings.update'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['notify_email_events.admin.pending']);
});

test('admin can reset settings to defaults and it is audited', function () {
    $admin = User::factory()->admin()->create();

    Setting::query()->updateOrCreate(
        ['key' => 'min_duration_minutes'],
        ['value' => 1, 'updated_by' => $admin->id],
    );

    $this->actingAs($admin)
        ->post(route('admin.settings.reset'))
        ->assertRedirect();

    $stored = Setting::query()->find('min_duration_minutes');
    expect($stored?->value)->toBe(SettingsSchema::defaults()['min_duration_minutes']);

    expect(AuditEvent::query()
        ->where('event_type', 'settings.reset_to_defaults')
        ->where('actor_id', $admin->id)
        ->exists()
    )->toBeTrue();
});

test('audit page renders without data props', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.audit.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Audit')
        );
});

test('artifact retry is audited', function () {
    Queue::fake();

    $admin = User::factory()->admin()->create();
    $reservation = Reservation::factory()->create();

    $artifact = ReservationArtifact::factory()->create([
        'reservation_id' => $reservation->id,
        'kind' => ReservationArtifactKind::EmailStudent,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.artifacts.retry', $artifact))
        ->assertRedirect();

    Queue::assertPushed(SendReservationEmail::class, function (SendReservationEmail $job) use ($artifact): bool {
        return $job->artifactId === $artifact->id;
    });

    expect(AuditEvent::query()
        ->where('event_type', 'artifact.retried')
        ->where('subject_type', ReservationArtifact::class)
        ->where('subject_id', $artifact->id)
        ->exists()
    )->toBeTrue();
});

test('admin settings update rejects opening hours where close is before open', function () {
    $admin = User::factory()->admin()->create();

    $payload = SettingsSchema::defaults();
    $payload['opening_hours']['mon'] = ['open' => '22:00', 'close' => '08:00'];

    $this->actingAs($admin)
        ->putJson(route('admin.settings.update'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['opening_hours.mon.close']);
});

test('admin settings update rejects opening hours where close equals open', function () {
    $admin = User::factory()->admin()->create();

    $payload = SettingsSchema::defaults();
    $payload['opening_hours']['mon'] = ['open' => '10:00', 'close' => '10:00'];

    $this->actingAs($admin)
        ->putJson(route('admin.settings.update'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['opening_hours.mon.close']);
});

test('blade layout includes csrf meta tag', function () {
    $this->get(route('calendar.public'))
        ->assertOk()
        ->assertSee('<meta name="csrf-token" content="', false);
});
