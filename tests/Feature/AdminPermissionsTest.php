<?php

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

test('students cannot access admin routes', function () {
    $student = User::factory()->create();

    $this->actingAs($student)
        ->get(route('admin.requests.index'))
        ->assertForbidden();

    $this->actingAs($student)
        ->getJson(route('api.admin.requests'))
        ->assertForbidden();
});

test('operators can decide requests but cannot manage settings', function () {
    Queue::fake();

    $operator = User::factory()->operator()->create();
    $reservation = Reservation::factory()->create();

    $this->actingAs($operator)
        ->get(route('admin.requests.index'))
        ->assertOk();

    $this->actingAs($operator)
        ->post(route('admin.requests.approve', $reservation))
        ->assertRedirect(route('admin.requests.index'));

    $this->actingAs($operator)
        ->get(route('admin.settings.edit'))
        ->assertForbidden();
});

test('auditors can view audit but cannot decide requests', function () {
    $auditor = User::factory()->auditor()->create();
    $reservation = Reservation::factory()->create();

    $this->actingAs($auditor)
        ->get(route('admin.audit.index'))
        ->assertOk();

    $this->actingAs($auditor)
        ->post(route('admin.requests.approve', $reservation))
        ->assertForbidden();
});
