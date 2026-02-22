<?php

use App\Actions\Settings\SettingsDefaults;
use App\Jobs\GenerateReservationPdf;
use App\Models\AuditEvent;
use App\Models\Enums\ReservationArtifactKind;
use App\Models\Reservation;
use App\Models\ReservationArtifact;
use App\Models\Setting;
use App\Models\User;
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
