<?php

use App\Models\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;

test('admin requests endpoint returns paginated structure', function () {
    $admin = User::factory()->admin()->create();

    Reservation::factory()->count(3)->create();

    $this->actingAs($admin)
        ->getJson(route('api.admin.requests'))
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

test('admin requests endpoint paginates results', function () {
    $admin = User::factory()->admin()->create();

    Reservation::factory()->count(20)->create();

    $page1 = $this->actingAs($admin)
        ->getJson(route('api.admin.requests'))
        ->assertOk();

    expect($page1->json('data'))->toHaveCount(15);
    expect($page1->json('next_page_url'))->not->toBeNull();

    $page2 = $this->actingAs($admin)
        ->getJson(route('api.admin.requests', ['page' => 2]))
        ->assertOk();

    expect($page2->json('data'))->toHaveCount(5);
    expect($page2->json('next_page_url'))->toBeNull();
});

test('admin requests endpoint includes created_at timestamp', function () {
    $admin = User::factory()->admin()->create();

    Reservation::factory()->create();

    $response = $this->actingAs($admin)
        ->getJson(route('api.admin.requests'))
        ->assertOk();

    expect($response->json('data.0'))->toHaveKey('created_at');
    expect($response->json('data.0.created_at'))->not->toBeNull();
});

test('admin requests endpoint only returns pending reservations', function () {
    $admin = User::factory()->admin()->create();

    Reservation::factory()->count(2)->create();
    Reservation::factory()->create(['status' => ReservationStatus::Approved]);
    Reservation::factory()->create(['status' => ReservationStatus::Rejected]);
    Reservation::factory()->create(['status' => ReservationStatus::Cancelled]);

    $response = $this->actingAs($admin)
        ->getJson(route('api.admin.requests'))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});
