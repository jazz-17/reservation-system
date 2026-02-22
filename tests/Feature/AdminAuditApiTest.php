<?php

use App\Actions\Settings\SettingsDefaults;
use App\Models\AuditEvent;
use App\Models\Reservation;
use App\Models\User;
use Carbon\CarbonImmutable;

test('unauthenticated user cannot access audit api', function () {
    $this->getJson(route('api.admin.audit'))
        ->assertUnauthorized();
});

test('non-admin user cannot access audit api', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('api.admin.audit'))
        ->assertForbidden();
});

test('admin can access audit api and receives json', function () {
    $admin = User::factory()->admin()->create();

    AuditEvent::query()->create([
        'event_type' => 'settings.updated',
        'actor_id' => $admin->id,
        'subject_type' => null,
        'subject_id' => null,
        'metadata' => ['changed_keys' => ['timezone']],
    ]);

    $this->actingAs($admin)
        ->getJson(route('api.admin.audit'))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'event_type', 'actor_id', 'created_at']],
            'eventTypes',
        ])
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['event_type' => 'settings.updated']);
});

test('audit api filters by event type', function () {
    $admin = User::factory()->admin()->create();

    AuditEvent::query()->create([
        'event_type' => 'settings.updated',
        'actor_id' => $admin->id,
        'subject_type' => null,
        'subject_id' => null,
        'metadata' => null,
    ]);

    AuditEvent::query()->create([
        'event_type' => 'reservation.created',
        'actor_id' => $admin->id,
        'subject_type' => Reservation::class,
        'subject_id' => 1,
        'metadata' => null,
    ]);

    $this->actingAs($admin)
        ->getJson(route('api.admin.audit', ['event_type' => 'settings.updated']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.event_type', 'settings.updated');
});

test('audit api filters by date range', function () {
    $admin = User::factory()->admin()->create();

    $timezone = SettingsDefaults::values()['timezone'];

    $includedCreatedAt = CarbonImmutable::parse('2026-02-10 10:00', $timezone)->setTimezone('UTC');
    $excludedCreatedAt = CarbonImmutable::parse('2026-02-11 10:00', $timezone)->setTimezone('UTC');

    AuditEvent::query()->create([
        'event_type' => 'settings.updated',
        'actor_id' => $admin->id,
        'subject_type' => null,
        'subject_id' => null,
        'metadata' => null,
        'created_at' => $includedCreatedAt,
    ]);

    AuditEvent::query()->create([
        'event_type' => 'settings.updated',
        'actor_id' => $admin->id,
        'subject_type' => null,
        'subject_id' => null,
        'metadata' => null,
        'created_at' => $excludedCreatedAt,
    ]);

    $this->actingAs($admin)
        ->getJson(route('api.admin.audit', ['from' => '2026-02-10', 'to' => '2026-02-10']))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('audit api returns event types list', function () {
    $admin = User::factory()->admin()->create();

    AuditEvent::query()->create([
        'event_type' => 'settings.updated',
        'actor_id' => $admin->id,
        'subject_type' => null,
        'subject_id' => null,
        'metadata' => null,
    ]);

    AuditEvent::query()->create([
        'event_type' => 'reservation.created',
        'actor_id' => $admin->id,
        'subject_type' => Reservation::class,
        'subject_id' => 1,
        'metadata' => null,
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('api.admin.audit'))
        ->assertOk();

    $eventTypes = $response->json('eventTypes');
    expect($eventTypes)->toContain('reservation.created', 'settings.updated');
});
