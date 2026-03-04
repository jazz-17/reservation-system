<?php

use App\Models\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;

test('admin history endpoint returns paginated structure', function () {
    $admin = User::factory()->admin()->create();

    Reservation::factory()->count(3)->create();

    $this->actingAs($admin)
        ->getJson(route('api.admin.history'))
        ->assertOk()
        ->assertJsonStructure([
            'current_page',
            'data',
            'first_page_url',
            'per_page',
            'next_page_url',
            'prev_page_url',
        ]);
});

test('admin history endpoint paginates results', function () {
    $admin = User::factory()->admin()->create();

    Reservation::factory()->count(30)->create();

    $page1 = $this->actingAs($admin)
        ->getJson(route('api.admin.history'))
        ->assertOk();

    expect($page1->json('data'))->toHaveCount(25);
    expect($page1->json('next_page_url'))->not->toBeNull();

    $page2 = $this->actingAs($admin)
        ->getJson(route('api.admin.history', ['page' => 2]))
        ->assertOk();

    expect($page2->json('data'))->toHaveCount(5);
    expect($page2->json('next_page_url'))->toBeNull();
});

test('admin history endpoint includes timestamp fields', function () {
    $admin = User::factory()->admin()->create();

    Reservation::factory()->create();
    Reservation::factory()->create([
        'status' => ReservationStatus::Approved,
        'decided_at' => now(),
        'decided_by' => $admin->id,
    ]);
    Reservation::factory()->create([
        'status' => ReservationStatus::Cancelled,
        'cancelled_at' => now(),
        'cancelled_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('api.admin.history'))
        ->assertOk();

    foreach ($response->json('data') as $item) {
        expect($item)->toHaveKeys(['created_at', 'decided_at', 'cancelled_at']);
    }
});

test('admin history endpoint filters by status with pagination', function () {
    $admin = User::factory()->admin()->create();

    Reservation::factory()->count(5)->create();
    Reservation::factory()->count(3)->create(['status' => ReservationStatus::Rejected]);

    $response = $this->actingAs($admin)
        ->getJson(route('api.admin.history', ['status' => 'pending']))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(5);

    foreach ($response->json('data') as $item) {
        expect($item['status'])->toBe('pending');
    }
});
