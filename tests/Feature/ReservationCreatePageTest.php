<?php

use App\Models\User;

test('reservation create page passes all expected props', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('reservations.create'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('reservations/Create')
        ->has('opening_hours')
        ->has('min_duration_minutes')
        ->has('max_duration_minutes')
        ->has('lead_time_min_hours')
        ->has('lead_time_max_days')
    );
});

test('store reservation returns Spanish messages for missing fields', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('reservations.store'), [
        'starts_at' => '',
        'ends_at' => '',
    ]);

    $response->assertSessionHasErrors(['starts_at', 'ends_at']);

    $errors = session('errors')->getBag('default');
    expect($errors->first('starts_at'))->toContain('hora de inicio');
    expect($errors->first('ends_at'))->toContain('hora de fin');
});

test('store reservation returns Spanish message when ends_at is before starts_at', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('reservations.store'), [
        'starts_at' => '2026-03-10 15:00',
        'ends_at' => '2026-03-10 14:00',
    ]);

    $response->assertSessionHasErrors('ends_at');

    $errors = session('errors')->getBag('default');
    expect($errors->first('ends_at'))->toContain('posterior');
});
