<?php

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
        'metadata' => ['changed_keys' => ['min_duration_minutes']],
    ]);

    $this->actingAs($admin)
        ->getJson(route('api.admin.audit'))
        ->assertOk()
        ->assertJsonStructure([
            'current_page',
            'data' => [['id', 'event_type', 'actor_id', 'created_at']],
            'first_page_url',
            'per_page',
            'next_page_url',
            'prev_page_url',
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

    $timezone = (string) config('app.timezone', 'America/Lima');

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

test('audit api paginates results and includes eventTypes on each page', function () {
    $admin = User::factory()->admin()->create();

    for ($i = 0; $i < 30; $i++) {
        AuditEvent::query()->create([
            'event_type' => 'settings.updated',
            'actor_id' => $admin->id,
            'subject_type' => null,
            'subject_id' => null,
            'metadata' => null,
        ]);
    }

    $page1 = $this->actingAs($admin)
        ->getJson(route('api.admin.audit'))
        ->assertOk();

    expect($page1->json('data'))->toHaveCount(25);
    expect($page1->json('next_page_url'))->not->toBeNull();
    expect($page1->json('eventTypes'))->toContain('settings.updated');

    $page2 = $this->actingAs($admin)
        ->getJson(route('api.admin.audit', ['page' => 2]))
        ->assertOk();

    expect($page2->json('data'))->toHaveCount(5);
    expect($page2->json('next_page_url'))->toBeNull();
    expect($page2->json('eventTypes'))->toContain('settings.updated');
});
