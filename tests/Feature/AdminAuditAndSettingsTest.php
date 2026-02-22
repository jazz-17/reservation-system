<?php

use App\Actions\Settings\SettingsDefaults;
use App\Jobs\GenerateReservationPdf;
use App\Models\AuditEvent;
use App\Models\Enums\ReservationArtifactKind;
use App\Models\Reservation;
use App\Models\ReservationArtifact;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

test('admin settings updates are audited with changed keys', function () {
    $admin = User::factory()->admin()->create();

    $payload = SettingsDefaults::values();
    $payload['timezone'] = 'UTC';

    $response = $this->actingAs($admin)
        ->putJson(route('admin.settings.update'), $payload)
        ->assertRedirect();

    $response->assertSessionHasNoErrors();

    $stored = Setting::query()->find('timezone');
    expect($stored)->not->toBeNull();
    expect($stored?->value)->toBe('UTC');

    $event = AuditEvent::query()
        ->where('event_type', 'settings.updated')
        ->latest('created_at')
        ->first();

    expect($event)->not->toBeNull();
    expect($event?->actor_id)->toBe($admin->id);
    expect($event?->metadata['changed_keys'] ?? [])->toContain('timezone');
});

test('admin settings update validation rejects invalid values', function () {
    $admin = User::factory()->admin()->create();

    $payload = SettingsDefaults::values();
    $payload['lead_time_max_days'] = 0;

    $this->actingAs($admin)
        ->putJson(route('admin.settings.update'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lead_time_max_days']);

    expect(AuditEvent::query()->where('event_type', 'settings.updated')->exists())->toBeFalse();
});

test('audit page filters by event type and date range', function () {
    $admin = User::factory()->admin()->create();
    $actor = User::factory()->create();

    $timezone = SettingsDefaults::values()['timezone'];

    $includedCreatedAt = CarbonImmutable::parse('2026-02-10 10:00', $timezone)->setTimezone('UTC');
    $excludedCreatedAt = CarbonImmutable::parse('2026-02-11 10:00', $timezone)->setTimezone('UTC');

    AuditEvent::query()->create([
        'event_type' => 'settings.updated',
        'actor_id' => $actor->id,
        'subject_type' => null,
        'subject_id' => null,
        'metadata' => ['changed_keys' => ['timezone']],
        'created_at' => $includedCreatedAt,
    ]);

    AuditEvent::query()->create([
        'event_type' => 'reservation.created',
        'actor_id' => $actor->id,
        'subject_type' => Reservation::class,
        'subject_id' => 1,
        'metadata' => null,
        'created_at' => $includedCreatedAt,
    ]);

    AuditEvent::query()->create([
        'event_type' => 'settings.updated',
        'actor_id' => $actor->id,
        'subject_type' => null,
        'subject_id' => null,
        'metadata' => ['changed_keys' => ['pdf_template']],
        'created_at' => $excludedCreatedAt,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.audit.index', [
            'event_type' => 'settings.updated',
            'from' => '2026-02-10',
            'to' => '2026-02-10',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Audit')
            ->where('filters.event_type', 'settings.updated')
            ->where('filters.from', '2026-02-10')
            ->where('filters.to', '2026-02-10')
            ->has('events', 1)
            ->where('events.0.actor.name', $actor->name)
            ->where('events.0.event_type', 'settings.updated')
        );
});

test('artifact retry is audited', function () {
    Queue::fake();

    $admin = User::factory()->admin()->create();
    $reservation = Reservation::factory()->create();

    $artifact = ReservationArtifact::factory()->create([
        'reservation_id' => $reservation->id,
        'kind' => ReservationArtifactKind::Pdf,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.artifacts.retry', $artifact))
        ->assertRedirect();

    Queue::assertPushed(GenerateReservationPdf::class, function (GenerateReservationPdf $job) use ($artifact): bool {
        return $job->artifactId === $artifact->id;
    });

    expect(AuditEvent::query()
        ->where('event_type', 'artifact.retried')
        ->where('subject_type', ReservationArtifact::class)
        ->where('subject_id', $artifact->id)
        ->exists()
    )->toBeTrue();
});
